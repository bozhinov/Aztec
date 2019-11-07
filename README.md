# Aztec (a Metzli-fork) - Work in progress

**Aztec** is a PHP library to generate Aztec 2D barcodes.

![Aztec code example](http://i.imgur.com/8JcHtOl.png)

## Usage

Using **Aztec** in your existing project is very easy:

```php

require 'vendor/autoload.php';

use Aztec\Encoder\Encoder;
use Aztec\Renderer\PngRenderer;

// ... some awesome code here ...

$code = Encoder::encode('Hello World!');
$renderer = new PngRenderer();

header('Content-Type: image/png');
echo $renderer->render($code);

```

## Contributing

If you want to get your hands dirty, great! Here's a couple of steps/guidelines:

- Fork this repository
- Add your changes & tests for those changes (in `tests/`).
- Remember to stick to the existing code style as best as possible. When in doubt, follow `PSR-2`.
- Send me a pull request!

If you don't want to go through all this, but still found something wrong or missing, please
let me know, and/or **open a new issue report** so that I or others may take care of it.

## Credits

**Aztec** is heavily based on [ZXing](https://github.com/zxing/zxing) and is basically a port of its Aztec encoding part.
