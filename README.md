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

$parser->parse('images/page1.png');
```