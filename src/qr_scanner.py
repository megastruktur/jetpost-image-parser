#!/usr/bin/env python3

import sys
import json

# apt install libzbar0
from PIL import Image
from pyzbar.pyzbar import decode

def main():
    if 1 < len(sys.argv):
        image_path = sys.argv[1]

        try:
            decoded = decode(Image.open(image_path))
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
        except:
            print("Bad image")

    else:
        print("No image provided")

if __name__ == "__main__":
    main()