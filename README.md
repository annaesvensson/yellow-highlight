<p align="right"><a href="README-de.md">Deutsch</a> &nbsp; <a href="README.md">English</a> &nbsp; <a href="README-sv.md">Svenska</a></p>

# Highlight 0.8.15

Highlight source code.

<p align="center"><img src="highlight-screenshot.png?raw=true" alt="Screenshot"></p>

## How to highlight source code

Wrap your code blocks in \`\`\` and add a language identifier.

The following programming languages are included: C, CPP, CSS, HTML, JavaScript, JSON, Lua, PHP, Python, YAML. You can download more [language files](https://github.com/scrivo/highlight.php/tree/master/src/Highlight/languages), rename and copy them into your `system/extensions` folder.

## Examples

Highlighting of JavaScript code:

    ``` javascript
    var ready = function() 
    {
        console.log("Hello world");
        // Add more JavaScript code here
    }
    window.addEventListener("DOMContentLoaded", ready, false);
    ```

Highlighting of HTML code, with and without line number:
    
    ``` html {.with-line-number}
    <body>
    <p>Hello world!</p>
    </body>
    ```

    ``` html {.without-line-number}
    <body>
    <p>Hello world!</p>
    </body>
    ```

## Settings

The following settings can be configured in file `system/extensions/yellow-system.ini`:

`HighlightLineNumber` = show line number, 1 or 0  
`HighlightAutodetectLanguages` = languages for automatic detection, comma separated  

## Acknowledgments

This extension includes [highlight.php 9.18.1.9](https://github.com/scrivo/highlight.php) by Ivan Sagalaev and Geert Bergman. Thanks for the great work.

## Installation

[Download extension](https://github.com/annaesvensson/yellow-highlight/archive/main.zip) and copy ZIP file into your `system/extensions` folder. [Learn more about extensions](https://github.com/annaesvensson/yellow-update).

## Developer

Anna Svensson. [Get help](https://datenstrom.se/yellow/help/).
