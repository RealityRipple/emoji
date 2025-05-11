# Emoji Directory
A directory of Emojis in 112x112 pixel PNG format, from Twemoji, Openmoji, Noto, Blobmoji, Facebook, Apple, JoyPixels, Toss Face, WhatsApp, and OneUI fonts.  

## Requirements
 - Building the image list with `populate.php` requires `php-cli`. No extensions are required.  
 
 - Inkscape is required to generate PNG images.  

## Resource Usage

### List
Access the active list of Emojis at  
 > `//cdn.jsdelivr.net/gh/realityripple/emoji/list.json`  

The minified version at `list.min.json` has all entry attributes stripped except `"aliases"`, which is renamed `"a"`.  

Redirects with `"target"` and `"status"` attributes are renamed `"t"` and `"s"` respectively, and the status is represented by a number:  
 - `unqualified` = `-1`  
 
 - `minimally-qualified` = `0`  
 
 - `fully-qualified` = `1` (unused at present)  

Additionally, any entries with no attributes are set to an integer value of `1` to further reduce size.  

### Images
Retrieve the required emoji:  
 > `//cdn.jsdelivr.net/gh/realityripple/emoji/%FONT%/%EMOJI_ID%.png`  

Where:  

 - `%FONT%` is `twemoji`, `openmoji`, `noto`, `blob`, `facebook`, `apple`, `joypixels`, `tossface`, `whatsapp`, or `oneui`  

 - `%EMOJI_ID%` is the lower-case, hyphen-separated hexadecimal representation of the Emoji, as listed in the JSON table. `minimally-qualified` and `unqualified` status entries must use the target value of their `fully-qualified` counterpart  

## API

Use the following in the `<head>` tag of your HTML document(s):

```html
<script src="//cdn.jsdelivr.net/gh/realityripple/emoji/remoji.min.js" crossorigin="anonymous"></script>
```

This guarantees that you will always use the latest version of the library.

Following are all the methods exposed in the `remoji` namespace.

### remoji.parse( ... ) V1

This is the main parsing utility and has 3 overloads per parsing type.

Although there are two kinds of parsing supported by this utility, we recommend you use [DOM parsing](#dom-parsing), explained below. Each type of parsing accepts a callback to generate an image source or an options object with parsing info.

The second kind of parsing is string parsing, explained in the legacy documentation [here](https://github.com/jdecked/twemoji/blob/main/LEGACY.md#string-parsing). This is unrecommended because this method does not sanitize the string or otherwise prevent malicious code from being executed; such sanitization is out of scope.

#### DOM parsing

If the first argument to `remoji.parse` is an `HTMLElement`, generated image tags will replace emoji that are **inside `#text` nodes only** without compromising surrounding nodes or listeners, and completely avoiding the usage of `innerHTML`.

If security is a major concern, this parsing can be considered the safest option but with a slight performance penalty due to DOM operations that are inevitably *costly*.

```js
var div = document.createElement('div');
div.textContent = 'I \u2764\uFE0F emoji!';
document.body.appendChild(div);

remoji.parse(document.body);

var img = div.querySelector('img');

// note the div is preserved
img.parentNode === div; // true

img.src;        // https://cdn.jsdelivr.net/gh/realityripple/emoji/twemoji/2764.png
img.alt;        // \u2764\uFE0F
img.className;  // emoji
img.draggable;  // false

```

All other overloads described for `string` are available in exactly the same way for DOM parsing.

### Object as parameter

Here's the list of properties accepted by the optional object that can be passed to the `parse` function.

```js
  {
    callback: Function,   // default the common replacer
    attributes: Function, // default returns {}
    base: string,         // default jsDelivr
    font: string,         // default "twemoji"
    className: string     // default "emoji"
  }
```

#### callback

The function to invoke in order to generate image `src`(s).

By default it is a function like the following one:

```js
function imageSourceGenerator(icon, options) {
  return ''.concat(
    options.base, // by default jsDelivr
    options.font, // by default "twemoji" string
    '/',
    icon          // the found emoji as code point
  );
}
```

#### base

The default url is the same as `remoji.base`, so if you modify the former, it will reflect as default for all parsed strings or nodes.

##### font

The default emoji font is the same as `remoji.font` which is `"twemoji"`.

If you modify the former, it will reflect as default for all parsed strings or nodes.

#### className

The default `class` for each generated image is `emoji`. It is possible to specify a different one through this property.

## Utilities

Basic utilities / helpers to convert code points to JavaScript surrogates and vice versa.

### remoji.convert.fromCodePoint()

For a given HEX codepoint, returns UTF-16 surrogate pairs.

```js
remoji.convert.fromCodePoint('1f1e8');
 // "\ud83c\udde8"
```

### remoji.convert.toCodePoint()

For given UTF-16 surrogate pairs, returns the equivalent HEX codepoint.

```js
 remoji.convert.toCodePoint('\ud83c\udde8\ud83c\uddf3');
 // "1f1e8-1f1f3"

 remoji.convert.toCodePoint('\ud83c\udde8\ud83c\uddf3', '~');
 // "1f1e8~1f1f3"
```

## Tips

### Inline Styles

If you'd like to size the emoji according to the surrounding text, you can add the following CSS to your stylesheet:

```css
img.emoji {
   height: 1em;
   width: 1em;
   margin: 0 .05em 0 .1em;
   vertical-align: -0.1em;
}
```

This will make sure emoji derive their width and height from the `font-size` of the text they're shown with. It also adds just a little bit of space before and after each emoji, and pulls them upwards a little bit for better optical alignment.

### UTF-8 Character Set

To properly support emoji, the document character set must be set to UTF-8. This can be done by including the following meta tag in the document `<head>`

```html
<meta charset="utf-8">
```

### Exclude Characters (V1)

To exclude certain characters from being replaced by remoji.js, call remoji.parse() with a callback, returning false for the specific unicode icon. For example:

```js
remoji.parse(document.body, {
    callback: function(icon, options, variant) {
        switch ( icon ) {
            case '00a9':    // © copyright
            case '00ae':    // ® registered trademark
            case '2122':    // ™ trademark
                return false;
        }
        return ''.concat(options.base, options.font, '/', icon, '.png');
    }
});
```

## Legacy API (V1)

If you're still using Twemoji's V1 API, you can find legacy documentation [here](https://github.com/jdecked/twemoji/blob/main/LEGACY.md).
