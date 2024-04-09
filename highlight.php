<?php
// Highlight extension, https://github.com/annaesvensson/yellow-highlight

class YellowHighlight {
    const VERSION = "0.9.2";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->system->setDefault("highlightLineNumber", "0");
        $this->yellow->system->setDefault("highlightAutodetectLanguages", "html, css, javascript, php");
    }
    
    // Handle page content element
    public function onParseContentElement($page, $name, $text, $attributes, $type) {
        $output = null;
        if ($this->isKnownLanguage($name) && $type=="code") {
            list($language, $text) = $this->processHighlight($name, $text);
            $htmlAttributes = $this->yellow->lookup->getHtmlAttributes(".highlight $attributes");
            if (!$this->isWithLineNumber($attributes)) {
                $output = "<pre$htmlAttributes><code class=\"".htmlspecialchars("language-$language")."\">".$text."</code></pre>";
            } else {
                $output = "<pre$htmlAttributes><code class=\"".htmlspecialchars("language-$language hljs-with-line-number")."\">";
                foreach ($this->yellow->toolbox->getTextLines($text) as $line) {
                    $output .= "<span class=\"hljs-line-number\"></span>$line";
                }
                $output .= "</code></pre>";
            }
        }
        return $output;
    }
    
    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $assetLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreAssetLocation");
            $output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$assetLocation}highlight.css\" />\n";
        }
        return $output;
    }
    
    // Process highlight
    public function processHighlight($language, $text) {
        $highlighter = new Highlighter(false);
        $autodetectLanguages = preg_split("/\s*,\s*/", $this->yellow->system->get("highlightAutodetectLanguages"));
        if (!in_array($language, $autodetectLanguages)) array_push($autodetectLanguages, $language);
        foreach ($autodetectLanguages as $autodetectLanguage) {
            list($languageId, $fileName) = $this->getLanguageInformation($autodetectLanguage);
            if (is_readable($fileName)) $highlighter->registerLanguage($languageId, $fileName);
        }
        try {
            $highlighter->setAutodetectLanguages($autodetectLanguages);
            $result = $highlighter->highlight($language, $text);
            $language = $result->language;
            $text = $result->value;
        } catch (DomainException $e) {
            $language = "unknown";
            $text = htmlspecialchars($text, ENT_NOQUOTES);
        }
        return array($language, $text);
    }
    
    // Return language information
    public function getLanguageInformation($language) {
        $aliases = array("c" => "cpp", "html" => "xml");
        $languageId = isset($aliases[$language]) ? $aliases[$language] : $language;
        $fileName = $this->yellow->system->get("coreWorkerDirectory")."highlight-$languageId.json";
        return array($languageId, $fileName);
    }
    
    // Check if shown with line number
    public function isWithLineNumber($attributes) {
        $lineNumber = (bool) $this->yellow->system->get("highlightLineNumber");
        if (preg_match("/.with-line-number/i", $attributes)) $lineNumber = true;
        if (preg_match("/.without-line-number/i", $attributes)) $lineNumber = false;
        return $lineNumber;
    }

    // Check if known language
    public function isKnownLanguage($name) {
        return !is_string_empty($name) ? is_readable($this->getLanguageInformation($name)[1]) : false;
    }    
}

/* Highlight.php, https://github.com/scrivo/highlight.php
 * Copyright (c)
 * - 2006-2013, Ivan Sagalaev (maniac@softwaremaniacs.org), highlight.js
 *              (original author)
 * - 2013-2019, Geert Bergman (geert@scrivo.nl), highlight.php
 * - 2014       Daniel Lynge, highlight.php (contributor)
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 3. Neither the name of "highlight.js", "highlight.php", nor the names of its
 *    contributors may be used to endorse or promote products derived from this
 *    software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

class Highlighter
{
    /**
     * @since 9.12.0.4
     */
    const SPAN_END_TAG = "</span>";

    /** @var bool Disable warnings thrown on PHP installations without multibyte functions available. */
    public static $DISABLE_MULTIBYTE_WARNING = false;

    /** @var bool */
    private $safeMode = true;

    // @TODO In v10.x, this value should be static to match highlight.js behavior
    /** @var array<string, mixed> */
    private $options;

    /** @var string */
    private $modeBuffer = "";

    /** @var string */
    private $result = "";

    /** @var Mode|null */
    private $top = null;

    /** @var Language|null */
    private $language = null;

    /** @var int */
    private $relevance = 0;

    /** @var bool */
    private $ignoreIllegals = false;

    /** @var array<string, Mode> */
    private $continuations = array();

    /** @var RegExMatch */
    private $lastMatch;

    /** @var string The current code we are highlighting */
    private $codeToHighlight;

    /** @var bool */
    private $needsMultibyteSupport = false;

    /** @var bool|null */
    private static $hasMultiByteSupport = null;

    /** @var bool */
    private static $hasThrownMultiByteWarning = false;

    /** @var string[] A list of all the bundled languages */
    private static $bundledLanguages = array();

    /** @var array<string, Language> A mapping of a language ID to a Language definition */
    private static $classMap = array();

    /** @var string[] A list of registered language IDs */
    private static $languages = array();

    /** @var array<string, string> A mapping from alias (key) to main language ID (value) */
    private static $aliases = array();

    /**
     * @param bool $loadAllLanguages If true, will automatically register all languages distributed with this library.
     *                               If false, user must explicitly register languages by calling `registerLanguage()`.
     *
     * @since 9.18.1.4 added `$loadAllLanguages` parameter
     * @see Highlighter::registerLanguage()
     */
    public function __construct($loadAllLanguages = true)
    {
        $this->lastMatch = new RegExMatch(array());
        $this->lastMatch->type = "";
        $this->lastMatch->rule = null;

        // @TODO In v10.x, remove the default value for the `languages` value to follow highlight.js behavior
        $this->options = array(
            'classPrefix' => 'hljs-',
            'tabReplace' => null,
            'useBR' => false,
            'languages' => array(
                "xml", "json", "javascript", "css", "php", "http",
            ),
        );

        if ($loadAllLanguages) {
            self::registerAllLanguages();
        }
    }

    /**
     * Return a list of all available languages bundled with this library.
     *
     * @since 9.18.1.4
     *
     * @return string[] An array of language names
     */
    public static function listBundledLanguages()
    {
        if (!empty(self::$bundledLanguages)) {
            return self::$bundledLanguages;
        }

        // Languages that take precedence in the classMap array. (I don't know why...)
        $bundledLanguages = array(
            "xml" => true,
            "django" => true,
            "javascript" => true,
            "matlab" => true,
            "cpp" => true,
        );

        $languagePath = __DIR__ . '/languages/';
        $d = @dir($languagePath);

        if (!$d) {
            throw new \RuntimeException('Could not read bundled language definition directory.');
        }

        // @TODO In 10.x, rewrite this as a generator yielding results
        while (($entry = $d->read()) !== false) {
            if (substr($entry, -5) === ".json") {
                $languageId = substr($entry, 0, -5);
                $filePath = $languagePath . $entry;

                if (is_readable($filePath)) {
                    $bundledLanguages[$languageId] = true;
                }
            }
        }

        $d->close();

        return self::$bundledLanguages = array_keys($bundledLanguages);
    }

    /**
     * Return a list of all the registered languages. Using this list in
     * setAutodetectLanguages will turn on auto-detection for all supported
     * languages.
     *
     * @since 9.18.1.4
     *
     * @param bool $includeAliases Specify whether language aliases should be
     *                             included as well
     *
     * @return string[] An array of language names
     */
    public static function listRegisteredLanguages($includeAliases = false)
    {
        if ($includeAliases === true) {
            return array_merge(self::$languages, array_keys(self::$aliases));
        }

        return self::$languages;
    }

    /**
     * Register all 185+ languages that are bundled in this library.
     *
     * To register languages individually, use `registerLanguage`.
     *
     * @since 9.18.1.4 Method is now public
     * @since 8.3.0.0
     * @see Highlighter::registerLanguage
     *
     * @return void
     */
    public static function registerAllLanguages()
    {
        // Languages that take precedence in the classMap array.
        $languagePath = __DIR__ . DIRECTORY_SEPARATOR . "languages" . DIRECTORY_SEPARATOR;
        foreach (array("xml", "django", "javascript", "matlab", "cpp") as $languageId) {
            $filePath = $languagePath . $languageId . ".json";
            if (is_readable($filePath)) {
                self::registerLanguage($languageId, $filePath);
            }
        }

        // @TODO In 10.x, call `listBundledLanguages()` instead when it's a generator
        $d = @dir($languagePath);
        if ($d) {
            while (($entry = $d->read()) !== false) {
                if (substr($entry, -5) === ".json") {
                    $languageId = substr($entry, 0, -5);
                    $filePath = $languagePath . $entry;
                    if (is_readable($filePath)) {
                        self::registerLanguage($languageId, $filePath);
                    }
                }
            }
            $d->close();
        }
    }

    /**
     * Register a language definition with the Highlighter's internal language
     * storage. Languages are stored in a static variable, so they'll be available
     * across all instances. You only need to register a language once.
     *
     * @param string $languageId The unique name of a language
     * @param string $filePath   The file path to the language definition
     * @param bool   $overwrite  Overwrite language if it already exists
     *
     * @return Language The object containing the definition for a language's markup
     */
    public static function registerLanguage($languageId, $filePath, $overwrite = false)
    {
        if (!isset(self::$classMap[$languageId]) || $overwrite) {
            $lang = new Language($languageId, $filePath);
            self::$classMap[$languageId] = $lang;

            self::$languages[] = $languageId;
            self::$languages = array_unique(self::$languages);

            if ($lang->aliases) {
                foreach ($lang->aliases as $alias) {
                    self::$aliases[$alias] = $languageId;
                }
            }
        }

        return self::$classMap[$languageId];
    }

    /**
     * Clear all registered languages.
     *
     * @since 9.18.1.4
     *
     * @return void
     */
    public static function clearAllLanguages()
    {
        self::$classMap = array();
        self::$languages = array();
        self::$aliases = array();
    }

    /**
     * @param RegEx|null $re
     * @param string     $lexeme
     *
     * @return bool
     */
    private function testRe($re, $lexeme)
    {
        if (!$re) {
            return false;
        }

        $lastIndex = $re->lastIndex;
        $result = $re->exec($lexeme);
        $re->lastIndex = $lastIndex;

        return $result && $result->index === 0;
    }

    /**
     * @param string $value
     *
     * @return RegEx
     */
    private function escapeRe($value)
    {
        return new RegEx(sprintf('/%s/um', preg_quote($value)));
    }

    /**
     * @param Mode   $mode
     * @param string $lexeme
     *
     * @return Mode|null
     */
    private function endOfMode($mode, $lexeme)
    {
        if ($this->testRe($mode->endRe, $lexeme)) {
            while ($mode->endsParent && $mode->parent) {
                $mode = $mode->parent;
            }

            return $mode;
        }

        if ($mode->endsWithParent) {
            return $this->endOfMode($mode->parent, $lexeme);
        }

        return null;
    }

    /**
     * @param Mode       $mode
     * @param RegExMatch $match
     *
     * @return mixed|null
     */
    private function keywordMatch($mode, $match)
    {
        $kwd = $this->language->case_insensitive ? $this->strToLower($match[0]) : $match[0];

        return isset($mode->keywords[$kwd]) ? $mode->keywords[$kwd] : null;
    }

    /**
     * @param string $className
     * @param string $insideSpan
     * @param bool   $leaveOpen
     * @param bool   $noPrefix
     *
     * @return string
     */
    private function buildSpan($className, $insideSpan, $leaveOpen = false, $noPrefix = false)
    {
        if (!$leaveOpen && $insideSpan === '') {
            return '';
        }

        if (!$className) {
            return $insideSpan;
        }

        $classPrefix = $noPrefix ? "" : $this->options['classPrefix'];
        $openSpan = "<span class=\"" . $classPrefix;
        $closeSpan = $leaveOpen ? "" : self::SPAN_END_TAG;

        $openSpan .= $className . "\">";

        return $openSpan . $insideSpan . $closeSpan;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function escape($value)
    {
        return htmlspecialchars($value, ENT_NOQUOTES);
    }

    /**
     * @return string
     */
    private function processKeywords()
    {
        if (!$this->top->keywords) {
            return $this->escape($this->modeBuffer);
        }

        $result = "";
        $lastIndex = 0;
        $this->top->lexemesRe->lastIndex = 0;
        $match = $this->top->lexemesRe->exec($this->modeBuffer);

        while ($match) {
            $result .= $this->escape(substr($this->modeBuffer, $lastIndex, $match->index - $lastIndex));
            $keyword_match = $this->keywordMatch($this->top, $match);

            if ($keyword_match) {
                $this->relevance += $keyword_match[1];
                $result .= $this->buildSpan($keyword_match[0], $this->escape($match[0]));
            } else {
                $result .= $this->escape($match[0]);
            }

            $lastIndex = $this->top->lexemesRe->lastIndex;
            $match = $this->top->lexemesRe->exec($this->modeBuffer);
        }

        return $result . $this->escape(substr($this->modeBuffer, $lastIndex));
    }

    /**
     * @return string
     */
    private function processSubLanguage()
    {
        try {
            $hl = new Highlighter();

            // @TODO in v10.x, this should no longer be necessary once `$options` is made static
            $hl->setAutodetectLanguages($this->options['languages']);
            $hl->setClassPrefix($this->options['classPrefix']);
            $hl->setTabReplace($this->options['tabReplace']);

            if (!$this->safeMode) {
                $hl->disableSafeMode();
            }

            $explicit = is_string($this->top->subLanguage);
            if ($explicit && !in_array($this->top->subLanguage, self::$languages)) {
                return $this->escape($this->modeBuffer);
            }

            if ($explicit) {
                $res = $hl->highlight(
                    $this->top->subLanguage,
                    $this->modeBuffer,
                    true,
                    isset($this->continuations[$this->top->subLanguage]) ? $this->continuations[$this->top->subLanguage] : null
                );
            } else {
                $res = $hl->highlightAuto(
                    $this->modeBuffer,
                    count($this->top->subLanguage) ? $this->top->subLanguage : null
                );
            }

            // Counting embedded language score towards the host language may be disabled
            // with zeroing the containing mode relevance. Use case in point is Markdown that
            // allows XML everywhere and makes every XML snippet to have a much larger Markdown
            // score.
            if ($this->top->relevance > 0) {
                $this->relevance += $res->relevance;
            }
            if ($explicit) {
                $this->continuations[$this->top->subLanguage] = $res->top;
            }

            return $this->buildSpan($res->language, $res->value, false, true);
        } catch (\Exception $e) {
            return $this->escape($this->modeBuffer);
        }
    }

    /**
     * @return void
     */
    private function processBuffer()
    {
        if (is_object($this->top) && $this->top->subLanguage) {
            $this->result .= $this->processSubLanguage();
        } else {
            $this->result .= $this->processKeywords();
        }

        $this->modeBuffer = '';
    }

    /**
     * @param Mode $mode
     *
     * @return void
     */
    private function startNewMode($mode)
    {
        $this->result .= $mode->className ? $this->buildSpan($mode->className, "", true) : "";

        $t = clone $mode;
        $t->parent = $this->top;
        $this->top = $t;
    }

    /**
     * @param RegExMatch $match
     *
     * @return int
     */
    private function doBeginMatch($match)
    {
        $lexeme = $match[0];
        $newMode = $match->rule;

        if ($newMode && $newMode->endSameAsBegin) {
            $newMode->endRe = $this->escapeRe($lexeme);
        }

        if ($newMode->skip) {
            $this->modeBuffer .= $lexeme;
        } else {
            if ($newMode->excludeBegin) {
                $this->modeBuffer .= $lexeme;
            }
            $this->processBuffer();
            if (!$newMode->returnBegin && !$newMode->excludeBegin) {
                $this->modeBuffer = $lexeme;
            }
        }
        $this->startNewMode($newMode);

        return $newMode->returnBegin ? 0 : strlen($lexeme);
    }

    /**
     * @param RegExMatch $match
     *
     * @return int|null
     */
    private function doEndMatch($match)
    {
        $lexeme = $match[0];
        $matchPlusRemainder = substr($this->codeToHighlight, $match->index);
        $endMode = $this->endOfMode($this->top, $matchPlusRemainder);

        if (!$endMode) {
            return null;
        }

        $origin = $this->top;
        if ($origin->skip) {
            $this->modeBuffer .= $lexeme;
        } else {
            if (!($origin->returnEnd || $origin->excludeEnd)) {
                $this->modeBuffer .= $lexeme;
            }
            $this->processBuffer();
            if ($origin->excludeEnd) {
                $this->modeBuffer = $lexeme;
            }
        }

        do {
            if ($this->top->className) {
                $this->result .= self::SPAN_END_TAG;
            }
            if (!$this->top->skip && !$this->top->subLanguage) {
                $this->relevance += $this->top->relevance;
            }
            $this->top = $this->top->parent;
        } while ($this->top !== $endMode->parent);

        if ($endMode->starts) {
            if ($endMode->endSameAsBegin) {
                $endMode->starts->endRe = $endMode->endRe;
            }

            $this->startNewMode($endMode->starts);
        }

        return $origin->returnEnd ? 0 : strlen($lexeme);
    }

    /**
     * @param string          $textBeforeMatch
     * @param RegExMatch|null $match
     *
     * @return int
     */
    private function processLexeme($textBeforeMatch, $match = null)
    {
        $lexeme = $match ? $match[0] : null;

        // add non-matched text to the current mode buffer
        $this->modeBuffer .= $textBeforeMatch;

        if ($lexeme === null) {
            $this->processBuffer();

            return 0;
        }

        // we've found a 0 width match and we're stuck, so we need to advance
        // this happens when we have badly behaved rules that have optional matchers to the degree that
        // sometimes they can end up matching nothing at all
        // Ref: https://github.com/highlightjs/highlight.js/issues/2140
        if ($this->lastMatch->type === "begin" && $match->type === "end" && $this->lastMatch->index === $match->index && $lexeme === "") {
            // spit the "skipped" character that our regex choked on back into the output sequence
            $this->modeBuffer .= substr($this->codeToHighlight, $match->index, 1);

            return 1;
        }
        $this->lastMatch = $match;

        if ($match->type === "begin") {
            return $this->doBeginMatch($match);
        } elseif ($match->type === "illegal" && !$this->ignoreIllegals) {
            // illegal match, we do not continue processing
            $_modeRaw = isset($this->top->className) ? $this->top->className : "<unnamed>";

            throw new \UnexpectedValueException("Illegal lexeme \"$lexeme\" for mode \"$_modeRaw\"");
        } elseif ($match->type === "end") {
            $processed = $this->doEndMatch($match);

            if ($processed !== null) {
                return $processed;
            }
        }

        // Why might be find ourselves here?  Only one occasion now.  An end match that was
        // triggered but could not be completed.  When might this happen?  When an `endSameasBegin`
        // rule sets the end rule to a specific match.  Since the overall mode termination rule that's
        // being used to scan the text isn't recompiled that means that any match that LOOKS like
        // the end (but is not, because it is not an exact match to the beginning) will
        // end up here.  A definite end match, but when `doEndMatch` tries to "reapply"
        // the end rule and fails to match, we wind up here, and just silently ignore the end.
        //
        // This causes no real harm other than stopping a few times too many.

        $this->modeBuffer .= $lexeme;

        return strlen($lexeme);
    }

    /**
     * Replace tabs for something more usable.
     *
     * @param string $code
     *
     * @return string
     */
    private function replaceTabs($code)
    {
        if ($this->options['tabReplace'] !== null) {
            return str_replace("\t", $this->options['tabReplace'], $code);
        }

        return $code;
    }

    private function checkMultibyteNecessity()
    {
        $this->needsMultibyteSupport = preg_match('/[^\x00-\x7F]/', $this->codeToHighlight) === 1;

        // If we aren't working with Unicode strings, then we default to `strtolower` since it's significantly faster
        //   https://github.com/scrivo/highlight.php/pull/92#pullrequestreview-782213861
        if (!$this->needsMultibyteSupport) {
            return;
        }

        if (self::$hasMultiByteSupport === null) {
            self::$hasMultiByteSupport = function_exists('mb_strtolower');
        }

        if (!self::$hasMultiByteSupport && !self::$hasThrownMultiByteWarning) {
            if (!self::$DISABLE_MULTIBYTE_WARNING) {
                trigger_error('Your code snippet has unicode characters but your PHP version does not have multibyte string support. You should install the `mbstring` PHP package or `symfony/polyfill-mbstring` composer package if you use unicode characters.', E_USER_WARNING);
            }

            self::$hasThrownMultiByteWarning = true;
        }
    }

    /**
     * Allow for graceful failure if the mb_strtolower function doesn't exist.
     *
     * @param string $str
     *
     * @return string
     */
    private function strToLower($str)
    {
        if ($this->needsMultibyteSupport && self::$hasMultiByteSupport) {
            return mb_strtolower($str);
        }

        return strtolower($str);
    }

    /**
     * Set the languages that will used for auto-detection. When using auto-
     * detection the code to highlight will be probed for every language in this
     * set. Limiting this set to only the languages you want to use will greatly
     * improve highlighting speed.
     *
     * @param string[] $set An array of language games to use for autodetection.
     *                      This defaults to a typical set Web development
     *                      languages.
     *
     * @return void
     */
    public function setAutodetectLanguages(array $set)
    {
        $this->options['languages'] = array_unique($set);
    }

    /**
     * Get the tab replacement string.
     *
     * @return string The tab replacement string
     */
    public function getTabReplace()
    {
        return $this->options['tabReplace'];
    }

    /**
     * Set the tab replacement string. This defaults to NULL: no tabs
     * will be replaced.
     *
     * @param string $tabReplace The tab replacement string
     *
     * @return void
     */
    public function setTabReplace($tabReplace)
    {
        $this->options['tabReplace'] = $tabReplace;
    }

    /**
     * Get the class prefix string.
     *
     * @return string The class prefix string
     */
    public function getClassPrefix()
    {
        return $this->options['classPrefix'];
    }

    /**
     * Set the class prefix string.
     *
     * @param string $classPrefix The class prefix string
     *
     * @return void
     */
    public function setClassPrefix($classPrefix)
    {
        $this->options['classPrefix'] = $classPrefix;
    }

    /**
     * @since 9.17.1.0
     *
     * @return void
     */
    public function enableSafeMode()
    {
        $this->safeMode = true;
    }

    /**
     * @since 9.17.1.0
     *
     * @return void
     */
    public function disableSafeMode()
    {
        $this->safeMode = false;
    }

    /**
     * @param string $name
     *
     * @return Language|null
     */
    private function getLanguage($name)
    {
        if (isset(self::$classMap[$name])) {
            return self::$classMap[$name];
        } elseif (isset(self::$aliases[$name]) && isset(self::$classMap[self::$aliases[$name]])) {
            return self::$classMap[self::$aliases[$name]];
        }

        return null;
    }

    /**
     * Determine whether or not a language definition supports auto detection.
     *
     * @param string $name Language name
     *
     * @return bool
     */
    private function autoDetection($name)
    {
        $lang = $this->getLanguage($name);

        return $lang && !$lang->disableAutodetect;
    }

    /**
     * Core highlighting function. Accepts a language name, or an alias, and a
     * string with the code to highlight. Returns an object with the following
     * properties:
     * - relevance (int)
     * - value (an HTML string with highlighting markup).
     *
     * @todo In v10.x, change the return type from \stdClass to HighlightResult
     *
     * @param string    $languageName
     * @param string    $code
     * @param bool      $ignoreIllegals
     * @param Mode|null $continuation
     *
     * @throws \DomainException if the requested language was not in this
     *                          Highlighter's language set
     * @throws \Exception       if an invalid regex was given in a language file
     *
     * @return HighlightResult|\stdClass
     */
    public function highlight($languageName, $code, $ignoreIllegals = true, $continuation = null)
    {
        $this->codeToHighlight = $code;
        $this->language = $this->getLanguage($languageName);

        if ($this->language === null) {
            throw new \DomainException("Unknown language: \"$languageName\"");
        }

        $this->checkMultibyteNecessity();

        $this->language->compile($this->safeMode);
        $this->top = $continuation ? $continuation : $this->language;
        $this->continuations = array();
        $this->result = "";

        for ($current = $this->top; $current !== $this->language; $current = $current->parent) {
            if ($current->className) {
                $this->result = $this->buildSpan($current->className, '', true) . $this->result;
            }
        }

        $this->modeBuffer = "";
        $this->relevance = 0;
        $this->ignoreIllegals = $ignoreIllegals;

        /** @var HighlightResult $res */
        $res = new \stdClass();
        $res->relevance = 0;
        $res->value = "";
        $res->language = "";
        $res->top = null;
        $res->errorRaised = null;

        try {
            $match = null;
            $count = 0;
            $index = 0;

            while ($this->top) {
                $this->top->terminators->lastIndex = $index;
                $match = $this->top->terminators->exec($this->codeToHighlight);

                if (!$match) {
                    break;
                }

                $count = $this->processLexeme(substr($this->codeToHighlight, $index, $match->index - $index), $match);
                $index = $match->index + $count;
            }

            $this->processLexeme(substr($this->codeToHighlight, $index));

            for ($current = $this->top; isset($current->parent); $current = $current->parent) {
                if ($current->className) {
                    $this->result .= self::SPAN_END_TAG;
                }
            }

            $res->relevance = $this->relevance;
            $res->value = $this->replaceTabs($this->result);
            $res->illegal = false;
            $res->language = $this->language->name;
            $res->top = $this->top;

            return $res;
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "Illegal") !== false) {
                $res->illegal = true;
                $res->relevance = 0;
                $res->value = $this->escape($this->codeToHighlight);

                return $res;
            } elseif ($this->safeMode) {
                $res->relevance = 0;
                $res->value = $this->escape($this->codeToHighlight);
                $res->language = $languageName;
                $res->top = $this->top;
                $res->errorRaised = $e;

                return $res;
            }

            throw $e;
        }
    }

    /**
     * Highlight the given code by highlighting the given code with each
     * registered language and then finding the match with highest accuracy.
     *
     * @param string        $code
     * @param string[]|null $languageSubset When set to null, this method will attempt to highlight $text with each
     *                                      language. Set this to an array of languages of your choice to limit the
     *                                      amount of languages to try.
     *
     * @throws \Exception       if an invalid regex was given in a language file
     * @throws \DomainException if the attempted language to check does not exist
     *
     * @return HighlightResult|\stdClass
     */
    public function highlightAuto($code, $languageSubset = null)
    {
        /** @var HighlightResult $result */
        $result = new \stdClass();
        $result->relevance = 0;
        $result->value = $this->escape($code);
        $result->language = "";
        $secondBest = clone $result;

        if ($languageSubset === null) {
            $optionsLanguages = $this->options['languages'];

            if (is_array($optionsLanguages) && count($optionsLanguages) > 0) {
                $languageSubset = $optionsLanguages;
            } else {
                $languageSubset = self::$languages;
            }
        }

        foreach ($languageSubset as $name) {
            if ($this->getLanguage($name) === null || !$this->autoDetection($name)) {
                continue;
            }

            $current = $this->highlight($name, $code, false);

            if ($current->relevance > $secondBest->relevance) {
                $secondBest = $current;
            }

            if ($current->relevance > $result->relevance) {
                $secondBest = $result;
                $result = $current;
            }
        }

        if ($secondBest->language) {
            $result->secondBest = $secondBest;
        }

        return $result;
    }

    /**
     * Return a list of all supported languages. Using this list in
     * setAutodetectLanguages will turn on autodetection for all supported
     * languages.
     *
     * @deprecated use `Highlighter::listRegisteredLanguages()` or `Highlighter::listBundledLanguages()` instead
     *
     * @param bool $include_aliases specify whether language aliases
     *                              should be included as well
     *
     * @since 9.18.1.4 Deprecated in favor of `Highlighter::listRegisteredLanguages()`
     *                 and `Highlighter::listBundledLanguages()`.
     * @since 9.12.0.3 The `$include_aliases` parameter was added
     * @since 8.3.0.0
     *
     * @return string[] An array of language names
     */
    public function listLanguages($include_aliases = false)
    {
        @trigger_error('This method is deprecated in favor `Highlighter::listRegisteredLanguages()` or `Highlighter::listBundledLanguages()`. This function will be removed in highlight.php 10.', E_USER_DEPRECATED);

        if (empty(self::$languages)) {
            trigger_error('No languages are registered, returning all bundled languages instead. You probably did not want this.', E_USER_WARNING);

            return self::listBundledLanguages();
        }

        if ($include_aliases === true) {
            return array_merge(self::$languages, array_keys(self::$aliases));
        }

        return self::$languages;
    }

    /**
     * Returns list of all available aliases for given language name.
     *
     * @param string $name name or alias of language to look-up
     *
     * @throws \DomainException if the requested language was not in this
     *                          Highlighter's language set
     *
     * @since 9.12.0.3
     *
     * @return string[] An array of all aliases associated with the requested
     *                  language name language. Passed-in name is included as
     *                  well.
     */
    public function getAliasesForLanguage($name)
    {
        $language = self::getLanguage($name);

        if ($language === null) {
            throw new \DomainException("Unknown language: $language");
        }

        if ($language->aliases === null) {
            return array($language->name);
        }

        return array_merge(array($language->name), $language->aliases);
    }
}
    
class JsonRef
{
    /**
     * Array to hold all data paths in the given JSON data.
     *
     * @var array<string, mixed>
     */
    private $paths = null;

    /**
     * Recurse through the data tree and fill an array of paths that reference
     * the nodes in the decoded JSON data structure.
     *
     * @param mixed  $s Decoded JSON data (decoded with json_decode)
     * @param string $r The current path key (for example: '#children.0').
     *
     * @return void
     */
    private function getPaths(&$s, $r = "#")
    {
        $this->paths[$r] = &$s;

        if (is_array($s) || is_object($s)) {
            foreach ($s as $k => &$v) {
                if ($k !== "\$ref") {
                    $this->getPaths($v, $r == "#" ? "#{$k}" : "{$r}.{$k}");
                }
            }
        }
    }

    /**
     * Recurse through the data tree and resolve all path references.
     *
     * @param mixed $s     Decoded JSON data (decoded with json_decode)
     * @param int   $limit
     * @param int   $depth
     *
     * @return void
     */
    private function resolvePathReferences(&$s, $limit = 20, $depth = 1)
    {
        if ($depth >= $limit) {
            return;
        }

        ++$depth;

        if (is_array($s) || is_object($s)) {
            foreach ($s as $k => &$v) {
                if ($k === "\$ref") {
                    $s = $this->paths[$v];
                } else {
                    $this->resolvePathReferences($v, $limit, $depth);
                }
            }
        }
    }

    /**
     * Decode JSON data that may contain path based references.
     *
     * @param object $json JSON data string or JSON data object
     *
     * @return void
     */
    public function decodeRef(&$json)
    {
        // Clear the path array.
        $this->paths = array();

        // Get all data paths.
        $this->getPaths($json);

        // Resolve all path references.
        $this->resolvePathReferences($json);
    }
}
    
abstract class Mode extends \stdClass
{
    /**
     * Fill in the missing properties that this Mode does not have.
     *
     * @internal
     *
     * @param \stdClass|null $obj
     *
     * @since 9.16.0.0
     *
     * @return void
     */
    public static function _normalize(&$obj)
    {
        // Don't overload our Modes if we've already normalized it
        if (isset($obj->__IS_COMPLETE)) {
            return;
        }

        if ($obj === null) {
            $obj = new \stdClass();
        }

        $patch = array(
            "begin" => true,
            "end" => true,
            "lexemes" => true,
            "illegal" => true,
        );

        // These values come in from JSON definition files
        $defaultValues = array(
            "case_insensitive" => false,
            "aliases" => array(),
            "className" => null,
            "begin" => null,
            "beginRe" => null,
            "end" => null,
            "endRe" => null,
            "beginKeywords" => null,
            "endsWithParent" => false,
            "endsParent" => false,
            "endSameAsBegin" => false,
            "lexemes" => null,
            "lexemesRe" => null,
            "keywords" => array(),
            "illegal" => null,
            "illegalRe" => null,
            "excludeBegin" => false,
            "excludeEnd" => false,
            "returnBegin" => false,
            "returnEnd" => false,
            "contains" => array(),
            "starts" => null,
            "variants" => array(),
            "relevance" => null,
            "subLanguage" => null,
            "skip" => false,
            "disableAutodetect" => false,
        );

        // These values are set at runtime
        $runTimeValues = array(
            "cachedVariants" => array(),
            "terminators" => null,
            "terminator_end" => "",
            "compiled" => false,
            "parent" => null,

            // This value is unique to highlight.php Modes
            "__IS_COMPLETE" => true,
        );

        foreach ($patch as $k => $v) {
            if (isset($obj->{$k})) {
                $obj->{$k} = str_replace("\\/", "/", $obj->{$k});
                $obj->{$k} = str_replace("/", "\\/", $obj->{$k});
            }
        }

        foreach ($defaultValues as $k => $v) {
            if (!isset($obj->{$k}) && is_object($obj)) {
                $obj->{$k} = $v;
            }
        }

        foreach ($runTimeValues as $k => $v) {
            if (is_object($obj)) {
                $obj->{$k} = $v;
            }
        }
    }

    /**
     * Set any deprecated properties values to their replacement values.
     *
     * @internal
     *
     * @param \stdClass $obj
     *
     * @return void
     */
    public static function _handleDeprecations(&$obj)
    {
        $deprecations = array(
            // @TODO Deprecated since 9.16.0.0; remove at 10.x
            'caseInsensitive' => 'case_insensitive',
            'terminatorEnd' => 'terminator_end',
        );

        foreach ($deprecations as $deprecated => $new) {
            $obj->{$deprecated} = &$obj->{$new};
        }
    }
}

class Language extends Mode
{
    /** @var string[] */
    private static $COMMON_KEYWORDS = array('of', 'and', 'for', 'in', 'not', 'or', 'if', 'then');

    /** @var string */
    public $name;

    /** @var Mode|null */
    private $mode = null;

    /**
     * @param string $lang
     * @param string $filePath
     *
     * @throws \InvalidArgumentException when the given $filePath is inaccessible
     */
    public function __construct($lang, $filePath)
    {
        $this->name = $lang;

        // We're loading the JSON definition file as an \stdClass object instead of an associative array. This is being
        // done to take advantage of objects being pass by reference automatically in PHP whereas arrays are pass by
        // value.
        $json = file_get_contents($filePath);

        if ($json === false) {
            throw new \InvalidArgumentException("Language file inaccessible: $filePath");
        }

        $this->mode = json_decode($json);
    }

    /**
     * @param string $name
     *
     * @return bool|Mode|null
     */
    public function __get($name)
    {
        if ($name === 'mode') {
            @trigger_error('The "mode" property will be removed in highlight.php 10.x', E_USER_DEPRECATED);

            return $this->mode;
        }

        if ($name === 'caseInsensitive') {
            @trigger_error('Due to compatibility requirements with highlight.js, use "case_insensitive" instead.', E_USER_DEPRECATED);

            if (isset($this->mode->case_insensitive)) {
                return $this->mode->case_insensitive;
            }

            return false;
        }

        if (isset($this->mode->{$name})) {
            return $this->mode->{$name};
        }

        return null;
    }

    /**
     * @param string $value
     * @param bool   $global
     *
     * @return RegEx
     */
    private function langRe($value, $global = false)
    {
        return RegExUtils::langRe($value, $global, $this->case_insensitive);
    }

    /**
     * Performs a shallow merge of multiple objects into one.
     *
     * @param Mode                 $params the objects to merge
     * @param array<string, mixed> ...$_
     *
     * @return Mode
     */
    private function inherit($params, $_ = array())
    {
        /** @var Mode $result */
        $result = new \stdClass();
        $objects = func_get_args();
        $parent = array_shift($objects);

        foreach ($parent as $key => $value) {
            $result->{$key} = $value;
        }

        foreach ($objects as $object) {
            foreach ($object as $key => $value) {
                $result->{$key} = $value;
            }
        }

        return $result;
    }

    /**
     * @param Mode|null $mode
     *
     * @return bool
     */
    private function dependencyOnParent($mode)
    {
        if (!$mode) {
            return false;
        }

        if (isset($mode->endsWithParent) && $mode->endsWithParent) {
            return $mode->endsWithParent;
        }

        return $this->dependencyOnParent(isset($mode->starts) ? $mode->starts : null);
    }

    /**
     * @param Mode $mode
     *
     * @return array<int, \stdClass|Mode>
     */
    private function expandOrCloneMode($mode)
    {
        if ($mode->variants && !$mode->cachedVariants) {
            $mode->cachedVariants = array();

            foreach ($mode->variants as $variant) {
                $mode->cachedVariants[] = $this->inherit($mode, array('variants' => null), $variant);
            }
        }

        // EXPAND
        // if we have variants then essentially "replace" the mode with the variants
        // this happens in compileMode, where this function is called from
        if ($mode->cachedVariants) {
            return $mode->cachedVariants;
        }

        // CLONE
        // if we have dependencies on parents then we need a unique
        // instance of ourselves, so we can be reused with many
        // different parents without issue
        if ($this->dependencyOnParent($mode)) {
            return array($this->inherit($mode, array(
                'starts' => $mode->starts ? $this->inherit($mode->starts) : null,
            )));
        }

        // highlight.php does not have a concept freezing our Modes

        // no special dependency issues, just return ourselves
        return array($mode);
    }

    /**
     * @param Mode      $mode
     * @param Mode|null $parent
     *
     * @return void
     */
    private function compileMode($mode, $parent = null)
    {
        Mode::_normalize($mode);

        if ($mode->compiled) {
            return;
        }

        $mode->compiled = true;
        $mode->keywords = $mode->keywords ? $mode->keywords : $mode->beginKeywords;

        if ($mode->keywords) {
            $mode->keywords = $this->compileKeywords($mode->keywords, (bool) $this->case_insensitive);
        }

        $mode->lexemesRe = $this->langRe($mode->lexemes ? $mode->lexemes : "\w+", true);

        if ($parent) {
            if ($mode->beginKeywords) {
                $mode->begin = "\\b(" . implode("|", explode(" ", $mode->beginKeywords)) . ")\\b";
            }

            if (!$mode->begin) {
                $mode->begin = "\B|\b";
            }

            $mode->beginRe = $this->langRe($mode->begin);

            if ($mode->endSameAsBegin) {
                $mode->end = $mode->begin;
            }

            if (!$mode->end && !$mode->endsWithParent) {
                $mode->end = "\B|\b";
            }

            if ($mode->end) {
                $mode->endRe = $this->langRe($mode->end);
            }

            $mode->terminator_end = $mode->end;

            if ($mode->endsWithParent && $parent->terminator_end) {
                $mode->terminator_end .= ($mode->end ? "|" : "") . $parent->terminator_end;
            }
        }

        if ($mode->illegal) {
            $mode->illegalRe = $this->langRe($mode->illegal);
        }

        if ($mode->relevance === null) {
            $mode->relevance = 1;
        }

        if (!$mode->contains) {
            $mode->contains = array();
        }

        /** @var Mode[] $expandedContains */
        $expandedContains = array();
        foreach ($mode->contains as &$c) {
            if ($c instanceof \stdClass) {
                Mode::_normalize($c);
            }

            $expandedContains = array_merge($expandedContains, $this->expandOrCloneMode(
                $c === 'self' ? $mode : $c
            ));
        }
        $mode->contains = $expandedContains;

        /** @var Mode $contain */
        foreach ($mode->contains as $contain) {
            $this->compileMode($contain, $mode);
        }

        if ($mode->starts) {
            $this->compileMode($mode->starts, $parent);
        }

        $terminators = new Terminators($this->case_insensitive);
        $mode->terminators = $terminators->_buildModeRegex($mode);

        Mode::_handleDeprecations($mode);
    }

    /**
     * @param array<string, string>|string $rawKeywords
     * @param bool                         $caseSensitive
     *
     * @return array<string, array<int, string|int>>
     */
    private function compileKeywords($rawKeywords, $caseSensitive)
    {
        /** @var array<string, array<int, string|int>> $compiledKeywords */
        $compiledKeywords = array();

        if (is_string($rawKeywords)) {
            $this->splitAndCompile("keyword", $rawKeywords, $compiledKeywords, $caseSensitive);
        } else {
            foreach ($rawKeywords as $className => $rawKeyword) {
                $this->splitAndCompile($className, $rawKeyword, $compiledKeywords, $caseSensitive);
            }
        }

        return $compiledKeywords;
    }

    /**
     * @param string                                $className
     * @param string                                $str
     * @param array<string, array<int, string|int>> $compiledKeywords
     * @param bool                                  $caseSensitive
     *
     * @return void
     */
    private function splitAndCompile($className, $str, array &$compiledKeywords, $caseSensitive)
    {
        if ($caseSensitive) {
            $str = strtolower($str);
        }

        $keywords = explode(' ', $str);

        foreach ($keywords as $keyword) {
            $pair = explode('|', $keyword);
            $providedScore = isset($pair[1]) ? $pair[1] : null;
            $compiledKeywords[$pair[0]] = array($className, $this->scoreForKeyword($pair[0], $providedScore));
        }
    }

    /**
     * @param string $keyword
     * @param string $providedScore
     *
     * @return int
     */
    private function scoreForKeyword($keyword, $providedScore)
    {
        if ($providedScore) {
            return (int) $providedScore;
        }

        return $this->commonKeyword($keyword) ? 0 : 1;
    }

    /**
     * @param string $word
     *
     * @return bool
     */
    private function commonKeyword($word)
    {
        return in_array(strtolower($word), self::$COMMON_KEYWORDS);
    }

    /**
     * Compile the Language definition.
     *
     * @param bool $safeMode
     *
     * @since 9.17.1.0 The 'safeMode' parameter was added.
     *
     * @return void
     */
    public function compile($safeMode)
    {
        if ($this->compiled) {
            return;
        }

        $jr = new JsonRef();
        $jr->decodeRef($this->mode);

        // self is not valid at the top-level
        if (isset($this->mode->contains) && !in_array("self", $this->mode->contains)) {
            if (!$safeMode) {
                throw new \LogicException("`self` is not supported at the top-level of a language.");
            }
            $this->mode->contains = array_filter($this->mode->contains, function ($mode) {
                return $mode !== "self";
            });
        }

        $this->compileMode($this->mode);
    }
}
    
final class RegEx
{
    /**
     * @var string
     */
    public $source;

    /**
     * @var int
     */
    public $lastIndex = 0;

    /**
     * @param RegEx|string $regex
     */
    public function __construct($regex)
    {
        $this->source = (string) $regex;
    }

    public function __toString()
    {
        return (string) $this->source;
    }

    /**
     * Run the regular expression against the given string.
     *
     * @since 9.16.0.0
     *
     * @param string $str the string to run this regular expression against
     *
     * @return RegExMatch|null
     */
    public function exec($str)
    {
        $index = null;
        $results = array();
        preg_match($this->source, $str, $results, PREG_OFFSET_CAPTURE, $this->lastIndex);

        if ($results === null || count($results) === 0) {
            return null;
        }

        foreach ($results as &$result) {
            if ($result[1] !== -1) {
                // Only save the index if it hasn't been set yet
                if ($index === null) {
                    $index = $result[1];
                }

                $result = $result[0];
            } else {
                $result = null;
            }
        }

        unset($result);

        $this->lastIndex += strlen($results[0]) + ($index - $this->lastIndex);

        $matches = new RegExMatch($results);
        $matches->index = isset($index) ? $index : 0;
        $matches->input = $str;

        return $matches;
    }
}

class RegExMatch implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /** @var array<int, string|null> */
    private $data;

    /** @var int */
    public $index;

    /** @var string */
    public $input;
    
    /** @var string */
    public $type;
    
    /** @var Mode|string */
    public $rule;

    /**
     * @param array<int, string|null> $results
     */
    public function __construct(array $results)
    {
        $this->data = $results;
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new \LogicException(__CLASS__ . ' instances are read-only.');
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new \LogicException(__CLASS__ . ' instances are read-only.');
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->data);
    }
}

abstract class RegExUtils
{
    /**
     * @param string $value
     * @param bool   $global
     * @param bool   $case_insensitive
     *
     * @return RegEx
     */
    public static function langRe($value, $global, $case_insensitive)
    {
        // PCRE allows us to change the definition of "new line." The
        // `(*ANYCRLF)` matches `\r`, `\n`, and `\r\n` for `$`
        //
        //   https://www.pcre.org/original/doc/html/pcrepattern.html

        // PCRE requires us to tell it the string can be UTF-8, so the 'u' modifier
        // is required. The `u` flag for PCRE is different from JS' unicode flag.

        $escaped = preg_replace('#(?<!\\\)/#um', '\\/', $value);
        $regex = "/(*ANYCRLF){$escaped}/um" . ($case_insensitive ? "i" : "");

        return new RegEx($regex);
    }
}

final class Terminators
{
    /** @var bool */
    private $caseInsensitive;

    /** @var array<int, Mode|string> */
    private $matchIndexes = array();

    /** @var RegEx|null */
    private $matcherRe = null;

    /** @var array<int, array<int, Mode|string>> */
    private $regexes = array();

    /** @var int */
    private $matchAt = 1;

    /** @var Mode */
    private $mode;

    /** @var int */
    public $lastIndex = 0;

    /**
     * @param bool $caseInsensitive
     */
    public function __construct($caseInsensitive)
    {
        $this->caseInsensitive = $caseInsensitive;
    }

    /**
     * @internal
     *
     * @param Mode $mode
     *
     * @return self
     */
    public function _buildModeRegex($mode)
    {
        $this->mode = $mode;
        $term = null;

        for ($i = 0; $i < count($mode->contains); ++$i) {
            $re = null;
            $term = $mode->contains[$i];

            if ($term->beginKeywords) {
                $re = "\.?(?:" . $term->begin . ")\.?";
            } else {
                $re = $term->begin;
            }

            $this->addRule($term, $re);
        }

        if ($mode->terminator_end) {
            $this->addRule('end', $mode->terminator_end);
        }

        if ($mode->illegal) {
            $this->addRule('illegal', $mode->illegal);
        }

        /** @var array<int, string> $terminators */
        $terminators = array();
        foreach ($this->regexes as $regex) {
            $terminators[] = $regex[1];
        }

        $this->matcherRe = $this->langRe($this->joinRe($terminators, '|'), true);
        $this->lastIndex = 0;

        return $this;
    }

    /**
     * @param string $s
     *
     * @return RegExMatch|null
     */
    public function exec($s)
    {
        if (count($this->regexes) === 0) {
            return null;
        }

        $this->matcherRe->lastIndex = $this->lastIndex;
        $match = $this->matcherRe->exec($s);
        if (!$match) {
            return null;
        }

        /** @var Mode|string $rule */
        $rule = null;
        for ($i = 0; $i < count($match); ++$i) {
            if ($match[$i] !== null && isset($this->matchIndexes[$i])) {
                $rule = $this->matchIndexes[$i];
                break;
            }
        }

        if (is_string($rule)) {
            $match->type = $rule;
        } else {
            $match->type = "begin";
            $match->rule = $rule;
        }

        return $match;
    }

    /**
     * @param string $value
     * @param bool   $global
     *
     * @return RegEx
     */
    private function langRe($value, $global = false)
    {
        return RegExUtils::langRe($value, $global, $this->caseInsensitive);
    }

    /**
     * @param Mode|string $rule
     * @param string      $regex
     *
     * @return void
     */
    private function addRule($rule, $regex)
    {
        $this->matchIndexes[$this->matchAt] = $rule;
        $this->regexes[] = array($rule, $regex);
        $this->matchAt += $this->reCountMatchGroups($regex) + 1;
    }

    /**
     * joinRe logically computes regexps.join(separator), but fixes the
     * backreferences so they continue to match.
     *
     * it also places each individual regular expression into it's own
     * match group, keeping track of the sequencing of those match groups
     * is currently an exercise for the caller. :-)
     *
     * @param array<int, string> $regexps
     * @param string             $separator
     *
     * @return string
     */
    private function joinRe($regexps, $separator)
    {
        // backreferenceRe matches an open parenthesis or backreference. To avoid
        // an incorrect parse, it additionally matches the following:
        // - [...] elements, where the meaning of parentheses and escapes change
        // - other escape sequences, so we do not misparse escape sequences as
        //   interesting elements
        // - non-matching or lookahead parentheses, which do not capture. These
        //   follow the '(' with a '?'.
        $backreferenceRe = '#\[(?:[^\\\\\]]|\\\.)*\]|\(\??|\\\([1-9][0-9]*)|\\\.#';
        $numCaptures = 0;
        $ret = '';

        $strLen = count($regexps);
        for ($i = 0; $i < $strLen; ++$i) {
            ++$numCaptures;
            $offset = $numCaptures;
            $re = $this->reStr($regexps[$i]);

            if ($i > 0) {
                $ret .= $separator;
            }

            $ret .= "(";

            while (strlen($re) > 0) {
                $matches = array();
                $matchFound = preg_match($backreferenceRe, $re, $matches, PREG_OFFSET_CAPTURE);

                if ($matchFound === 0) {
                    $ret .= $re;
                    break;
                }

                // PHP aliases to match the JS naming conventions
                $match = $matches[0];
                $index = $match[1];

                $ret .= substr($re, 0, $index);
                $re = substr($re, $index + strlen($match[0]));

                if (substr($match[0], 0, 1) === '\\' && isset($matches[1])) {
                    // Adjust the backreference.
                    $ret .= "\\" . strval(intval($matches[1][0]) + $offset);
                } else {
                    $ret .= $match[0];
                    if ($match[0] == "(") {
                        ++$numCaptures;
                    }
                }
            }

            $ret .= ")";
        }

        return $ret;
    }

    /**
     * @param RegEx|string $re
     *
     * @return mixed
     */
    private function reStr($re)
    {
        if ($re && isset($re->source)) {
            return $re->source;
        }

        return $re;
    }

    /**
     * @param RegEx|string $re
     *
     * @return int
     */
    private function reCountMatchGroups($re)
    {
        $results = array();
        $escaped = preg_replace('#(?<!\\\)/#um', '\\/', (string) $re);
        preg_match_all("/{$escaped}|/u", '', $results);

        return count($results) - 1;
    }
}
