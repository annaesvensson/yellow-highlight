<p align="right"><a href="README-de.md">Deutsch</a> &nbsp; <a href="README.md">English</a> &nbsp; <a href="README-sv.md">Svenska</a></p>

# Highlight 0.8.16

Markera källkod.

<p align="center"><img src="highlight-screenshot.png?raw=true" alt="Skärmdump"></p>

## Hur man installerar ett tillägg

[Ladda ner ZIP-filen](https://github.com/annaesvensson/yellow-highlight/archive/main.zip) och kopiera den till din `system/extensions` mapp. [Läs mer om tillägg](https://github.com/annaesvensson/yellow-update/tree/main/README-sv.md).

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

## Tack

Detta tilläg innehåller [highlight.php 9.18.1.10](https://github.com/scrivo/highlight.php) av Ivan Sagalaev och Geert Bergman. Tack för ett bra jobb.

## Utvecklare

Anna Svensson. [Få hjälp](https://datenstrom.se/sv/yellow/help/).