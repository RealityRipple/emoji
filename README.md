# Emoji Directory
A directory of Emojis in 112x112 pixel PNG format, from Twemoji, Openmoji, Noto, and Blobmoji fonts.  

## Requirements
 - Building the image list with `populate.php` requires `php-cli`. No extensions are required.  
 
 - Inkscape is required to generate PNG images.  

## Usage

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

 - `%FONT%` is `twemoji`, `openmoji`, `noto` or `blob`  

 - `%EMOJI_ID%` is the lower-case, hyphen-separated hexadecimal representation of the Emoji, as listed in the JSON table. `minimally-qualified` and `unqualified` status entries must use the target value of their `fully-qualified` counterpart  
