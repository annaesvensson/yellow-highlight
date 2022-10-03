<p align="right"><a href="README-de.md">Deutsch</a> &nbsp; <a href="README.md">English</a> &nbsp; <a href="README-sv.md">Svenska</a></p>

# Highlight 0.8.14

Quellcode hervorheben.

<p align="center"><img src="highlight-screenshot.png?raw=true" alt="Bildschirmfoto"></p>

## Wie man Quellcode hervorhebt

Wickle Codeblöcke in \`\`\` ein und fügen eine Sprachidentifizierung hinzu.

Die folgenden Programmiersprachen sind enthalten: C, CPP, CSS, HTML, JavaScript, JSON, Lua, PHP, Python, YAML. Du kannst weitere [Sprachdateien](https://github.com/scrivo/highlight.php/tree/master/src/Highlight/languages) herunterladen, umbenennen und in dein `system/extensions`-Verzeichnis kopieren.

## Beispiele

Hervorhebung von JavaScript-Quellcode:

    ``` javascript
    var ready = function() 
    {
        console.log("Hello world");
        // Add more JavaScript code here
    }
    window.addEventListener("DOMContentLoaded", ready, false);
    ```

Hervorhebung von HTML-Quellcode, mit und ohne Zeilennummer:
    
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

## Einstellungen

Die folgenden Einstellungen können in der Datei `system/extensions/yellow-system.ini` vorgenommen werden:

`HighlightLineNumber` = Zeilennummer anzeigen, 1 oder 0  
`HighlightAutodetectLanguages` = Sprachen zur automatischen Erkennung, durch Komma getrennt  

## Installation

[Erweiterung herunterladen](https://github.com/annaesvensson/yellow-highlight/archive/main.zip) und die Zip-Datei in dein `system/extensions`-Verzeichnis kopieren. Rechtsklick bei Safari.

Diese Erweiterung benutzt [highlight.php 9.18.1.9](https://github.com/scrivo/highlight.php) von Ivan Sagalaev und Geert Bergman.

## Entwickler

Anna Svensson. [Hilfe finden](https://datenstrom.se/de/yellow/help/).
