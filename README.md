# Installation
1. Install `libzbar0`:
   ```shell
    sudo apt update
    sudo apt install libzbar0
    ```
2. Include the library via composer (from repo)

# Usage example
```injectablephp
<?php

require_once 'vendor/autoload.php';

$parser = new \Jetpost\Parser();

$ScanImage = $parser->parse('images/page1.png');

var_dump($ScanImage);
```
### Response
```
object(Jetpost\ScanImage)#2 (6) {
  ["fromImage"]=> string(25) "parser/page1.png/from.png"
  ["shortMessageImage"] => string(34) "parser/page1.png/short_message.png"
  ["fullMessageImage"] => string(33) "parser/page1.png/full_message.png"
  ["phoneNumber"] => string(12) "18168785926"
  ["phoneNumberImage"] => string(26) "parser/page1.png/phone.png"
  ["errors"]=> array(0) {}
}
```