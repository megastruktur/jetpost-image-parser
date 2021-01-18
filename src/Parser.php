<?php

namespace Jetpost;

use InvalidArgumentException;
use stdClass;
use Zebra_Image;

class Parser {

  // Config
  protected string $tempDir = 'parser/';

  // User input
  protected string $imagePath;
  protected bool $validate;

  // Flags
  protected bool $qr_parsed = false;
  public bool $phone_is_parsed = false;

  // Props settings
  private string $imageTempDirName;
  private string $imageExtension;
  private string $imageInitialPath;
  private string $imageRotatedPath;
  private string $imagePhonePath;
  private string $imageShortMessagePath;
  private string $imageFullMessagePath;
  private string $imageFromPath;

  // Props dynamic
  protected stdClass $qr;
  protected array $errors = [];
  protected string $phone;


  /**
   * Parser constructor.
   */
  public function __construct(bool $validate = false)
  {

    $this->validate = $validate;
    $directory = pathinfo(__FILE__,PATHINFO_DIRNAME);
    // Chmod to execute later.
    chmod($directory . '/qr_scanner.py', 0750);

  }

  /**
   * Parse the image.
   * @param string $imagePath
   * @return ScanImage
   */
  public function parse(string $imagePath): ScanImage
  {

    $ScanImage = new ScanImage();

    if (file_exists($imagePath)) {
      $this->imagePath = $imagePath;
    }
    else {
      throw new InvalidArgumentException("No image at the provided path");
    }
    $this->createImageWorkingDir();

    // Get QR data to determine rotate degrees.
    $this->qr_parsed = $this->getQRData();
    if ($this->qr_parsed) {

      // Prepare image
      $this->prepareImage();

      // Get data
      $this->phone = $this->parsePhone();

      $ScanImage->phoneNumber = $this->phone;
      $ScanImage->phoneNumberImage = $this->imagePhonePath;
      $ScanImage->fromImage = $this->imageFromPath;
      $ScanImage->fullMessageImage = $this->imageFullMessagePath;
      $ScanImage->shortMessageImage = $this->imageShortMessagePath;
    }
    else {
      $this->errors[] = 'QR was not parsed';
    }

    $ScanImage->errors = $this->errors;

    return $ScanImage;
  }

  /**
   * Creates Image temp dir.
   */
  private function createImageWorkingDir()
  {
    // Create Temp dir for image
    $pathinfo = pathinfo($this->imagePath);
    $this->imageExtension = $pathinfo['extension'];
    $this->imageTempDirName = $this->tempDir . $pathinfo['basename'];
    $this->preparePathProps();

    // @todo Make sure to use Storage while in Laravel.
    mkdir($this->imageTempDirName, 0755, true);
    copy($this->imagePath, $this->imageInitialPath);
  }

  /**
   * Execute QR parser script and get QR code values.
   * @param bool $rotated
   * Already rotated?
   * @return bool
   */
  protected function getQRData(bool $rotated = false): bool
  {
    $directory = pathinfo(__FILE__,PATHINFO_DIRNAME);
    if ($rotated) {
      $image_path = $this->imageRotatedPath;
    }
    else {
      $image_path = $this->imageInitialPath;
    }

    $command = escapeshellcmd($directory . '/qr_scanner.py ' . $image_path);
    $qr_codes = json_decode(shell_exec($command));
    $this->qr = $qr_codes ?? new stdClass();

    return (bool) $this->qr;
  }

  /**
   * Prepares path properties for easier access.
   */
  private function preparePathProps() {
    $this->imageInitialPath = $this->imageTempDirName . '/initial.' . $this->imageExtension;
    $this->imageRotatedPath = $this->imageTempDirName . '/rotated.' . $this->imageExtension;
    $this->imagePhonePath = $this->imageTempDirName . '/phone.' . $this->imageExtension;
    $this->imageShortMessagePath = $this->imageTempDirName . '/short_message.' . $this->imageExtension;
    $this->imageFullMessagePath = $this->imageTempDirName . '/full_message.' . $this->imageExtension;
    $this->imageFromPath = $this->imageTempDirName . '/from.' . $this->imageExtension;

  }

  /**
   * Prepare image for parsing. Rotate, crop, etc.
   */
  protected function prepareImage()
  {
    // Rotate
    $rotate_degree = rad2deg(atan(abs($this->qr->right->top - $this->qr->left->top)/abs
      ($this->qr->right->left - $this->qr->left->left)));

    $image = new Zebra_Image();
    $image->source_path = $this->imageInitialPath;
    $image->target_path = $this->imageRotatedPath;
    $image->rotate(-$rotate_degree, '#FFFFFF');

    // Get QR data after the rotation because we need to correct the coordinates.
    $this->getQRData(true);

    // Crop Phone
    $image->source_path = $this->imageRotatedPath;
    $image->target_path = $this->imagePhonePath;
    $crop_x1 = $this->qr->left->left + 1.5 * $this->qr->left->width;
    $crop_y1 = $this->qr->left->top;
    $crop_x2 = $this->qr->right->left - 0.5 * $this->qr->right->width;
    $crop_y2 = $this->qr->right->top + $this->qr->right->height;
    $image->crop($crop_x1, $crop_y1, $crop_x2, $crop_y2);

    // Crop From
    $image->target_path = $this->imageFromPath;
    $crop_x1 = $this->qr->left->left + 3 * $this->qr->left->width;
    $crop_y1 = $this->qr->left->top + 1.2 * $this->qr->left->height;
    $crop_x2 = $this->qr->right->left + $this->qr->right->width;
    $crop_y2 = $this->qr->right->top + 1.6 * $this->qr->right->height;
    $image->crop($crop_x1, $crop_y1, $crop_x2, $crop_y2);

    // Crop Full Message
    $image->target_path = $this->imageFullMessagePath;
    $crop_x1 = $this->qr->left->left;
    $crop_y1 = $this->qr->left->top + 2 * $this->qr->left->height;
    $crop_x2 = $this->qr->right->left + $this->qr->right->width;
    $crop_y2 = $this->qr->right->top + 16 * $this->qr->right->height;
    $image->crop($crop_x1, $crop_y1, $crop_x2, $crop_y2);

    // Crop Short Message
    $image->target_path = $this->imageShortMessagePath;
    $crop_y2 = $this->qr->right->top + 5 * $this->qr->right->height;
    $image->crop($crop_x1, $crop_y1, $crop_x2, $crop_y2);
  }

  /**
   * Parse Phone with the help of SSOCR library.
   */
  protected function parsePhone(): string
  {

    $output = 'found too many digits';
    $digits = 8;
    $phone_parsed = '';

    while (strpos($output, 'found too many digits') === 0) {
      $command_txt = "ssocr remove_isolated dilation 1 closing 1 opening 1 --number-digits=$digits --charset=digits " .
        $this->imagePhonePath;
      $command = escapeshellcmd($command_txt);

      $output = shell_exec($command . ' 2>&1');

      $digits++;
    }

    // Process phone number
    if (strpos($output, 'found only') !== 0) {
      // Cut 1st character ("+" symbol is treated as 8)
      $phone_parsed = substr($output, 1);
    }

    if ($phone_parsed) {
      $this->phone_is_parsed = true;
    }
    else {
      $this->errors[] = 'Phone was not parsed';
    }

    // @todo Handle if NOThING found (couldn't reproduce)

    return $phone_parsed;
  }

  /**
   * Get errors.
   * @return array
   */
  public function getErrors(): array
  {
    return $this->errors;
  }

}