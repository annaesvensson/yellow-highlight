<p align="right"><a href="README-de.md">Deutsch</a> &nbsp; <a href="README.md">English</a> &nbsp; <a href="README-sv.md">Svenska</a></p>

# Highlight 0.8.14

Markera källkod.

<p align="center"><img src="highlight-screenshot.png?raw=true" alt="Skärmdump"></p>

## Hur man markerar källkod

Slå in dina kodblock i \`\`\` och lägg till en språkidentifierare. 

Följande programmeringsspråk ingår: C, CPP, CSS, HTML, JavaScript, JSON, Lua, PHP, Python, YAML. Du kan ladda ner fler [språkfiler](https://github.com/scrivo/highlight.php/tree/master/src/Highlight/languages), byta namn på och kopiera dem till din `system/extensions` mapp.

## Exempel

Markering av JavaScript-kod:

    ``` javascript
    var ready = function() 
    {
        console.log("Hello world");
        // Add more JavaScript code here
    }
    window.addEventListener("DOMContentLoaded", ready, false);
    ```

Markering av HTML-kod, med och utan radnummer:
    
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

## Inställningar

Följande inställningar kan konfigureras i filen `system/extensions/yellow-system.ini`:

`HighlightLineNumber` = visa radnummer, 1 eller 0  
`HighlightAutodetectLanguages` = språk för automatisk detektering, kommaseparerade  

## Installation

[Ladda ner tillägg](https://github.com/datenstrom/yellow-extensions/raw/master/downloads/highlight.zip) och kopiera zip-fil till din `system/extensions` mapp. Högerklicka om du använder Safari.

Detta tilläg använder [highlight.php 9.18.1.9](https://github.com/scrivo/highlight.php) av Ivan Sagalaev och Geert Bergman.

## Utvecklare

Datenstrom. [Få hjälp](https://datenstrom.se/sv/yellow/help/).