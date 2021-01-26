#!/usr/bin/env python3

import sys
import json
import cv2

# apt install libzbar0
from PIL import Image
from pyzbar.pyzbar import decode

def main():
    if 1 < len(sys.argv):
        image_path = sys.argv[1]

        try:
            kernel = cv2.getStructuringElement(cv2.MORPH_CROSS, (3,3))
            img = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)
            dst = img
            dst = cv2.morphologyEx(dst, cv2.MORPH_CLOSE, kernel)
            dst = cv2.morphologyEx(dst, cv2.MORPH_OPEN, kernel)

            # Print result image to screen. Press any key to proceed.
            # cv2.imshow('output', dst)
            # cv2.waitKey()
            # cv2.destroyAllWindows()

            decoded = decode(dst)
            qrs = []
            output = {
                "left": {},
                "right": {},
            }
            for qr in decoded:
                code = {
                    "data": qr.data.decode("utf-8"),
                    "left": qr.rect.left,
                    "top": qr.rect.top,
                    "width": qr.rect.width,
                    "height": qr.rect.height,
                }
                qrs.append(code)

            if qrs[0]["left"] > qrs[1]["left"] :
                output["left"] = qrs[1]
                output["right"] = qrs[0]
            else:
                output["left"] = qrs[0]
                output["right"] = qrs[1]

            print(json.dumps(output))
        except Exception as e:
            print(str(e))

    else:
        print("No image provided")

if __name__ == "__main__":
    main()