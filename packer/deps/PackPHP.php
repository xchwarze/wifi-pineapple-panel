<?php

/*
 * Minify PHP source
 * Based on algorithm by gelamu(ät)gmail(dt)com
 * http://php.net/manual/fr/function.php-strip-whitespace.php
 * 
 * @package Packer
 * @author Vallo Reima
 * @copyright (C)2015
 */

class PackPHP {

  private $obn = null;  /* obfuscation object */
  private $fle = '';    /* source file name */
  private $min = true;  /* pack flag */
  private static $IW = [ /* Whitespaces left and right from this signs can be ignored */
      T_CONCAT_EQUAL, // .=
      T_DOUBLE_ARROW, // =>
      T_BOOLEAN_AND, // &&
      T_BOOLEAN_OR, // ||
      T_IS_EQUAL, // ==
      T_IS_NOT_EQUAL, // != or <>
      T_IS_SMALLER_OR_EQUAL, // <=
      T_IS_GREATER_OR_EQUAL, // >=
      T_INC, // ++
      T_DEC, // --
      T_PLUS_EQUAL, // +=
      T_MINUS_EQUAL, // -=
      T_MUL_EQUAL, // *=
      T_DIV_EQUAL, // /=
      T_IS_IDENTICAL, // ===
      T_IS_NOT_IDENTICAL, // !==
      T_DOUBLE_COLON, // ::
      T_PAAMAYIM_NEKUDOTAYIM, // ::
      T_OBJECT_OPERATOR, // ->
      T_DOLLAR_OPEN_CURLY_BRACES, // ${
      T_AND_EQUAL, // &=
      T_MOD_EQUAL, // %=
      T_XOR_EQUAL, // ^=
      T_OR_EQUAL, // |=
      T_SL, // <<
      T_SR, // >>
      T_SL_EQUAL, // <<=
      T_SR_EQUAL, // >>=
  ];

  /**
   * @param string $source
   * @param array $options
   * @return mixed -- string - ok
   */
  public static function minify($source, $options = []) {
    $min = new self($options);
    return $min->process($source);
  }

  /**
   * save options
   * @param array $opts
   */
  public function __construct($opts) {
    if (isset($opts['min'])) {
      $this->min = $opts['min'] !== false; // compress yes/no
    }
    if (isset($opts['obn']) && !empty($opts['fle'])) {
      $this->obn = $opts['obn']; //obfuscator call
      $this->fle = $opts['fle']; //filename to save
    }
  }

  /**
   * compress code and register identifiers
   * @param string $php source
   * @return array
   */
  private function process($php) {
    $flg = $this->obn ? false : null; //php identifiers' collection
    if (mb_stripos(pathinfo($this->fle, PATHINFO_EXTENSION), 'php') !== 0) {//not php file
      $php = $this->Fix($php); //04.2017
    }
    $rlt = $this->Compress($php, $flg);
    if (!$this->min) {
      $rlt = $php;  // return not-packed
    }
    if ($flg) { // source has php tags
      call_user_func($this->obn, -1, $this->fle); // save filename
    }
    return $rlt;
  }

  /**
   * add a space if missing after the tag
   * @param string $src
   * @return string
   */
  private function Fix($src) {
    $t = '<?php';
    $l = strlen($t);
    $s = '';
    $n = mb_strlen($src);
    $i = 0;
    while ($i < $n) {
      $k = mb_stripos($src, $t, $i);
      if ($k === false) {
        $s .= mb_substr($src, $i, $n - $i);
        $i = $n;
      } else {//tag found
        $k += $l;
        $s .= mb_substr($src, $i, $k - $i);
        $i = $k;
        if ($i < $n && mb_substr($src, $i, 1) !== ' ') {
          $s .= ' ';//insert missing space
        }
      }
    }
    return $s;
  }

  /**
   * compress PHP source code
   * @param string $src
   * @param mixed $flg -- false - fix open tag
   *                      null - no check
   * @return string
   */
  private function Compress($src, &$flg) {
    $tokens = token_get_all($src);
    $new = "";
    $c = sizeof($tokens);
    $iw = false; // ignore whitespace
    $ls = "";    // last sign
    $ot = null;  // open tag
    for ($i = 0; $i < $c; $i++) {
      $token = $tokens[$i];
      if (is_array($token)) {
        list($tn, $ts) = $token; // tokens: number, string, line
        if ($tn == T_INLINE_HTML) { // token_name($tn)
          $new .= $ts;
          $iw = false;
        } else {
          if ($tn == T_OPEN_TAG) {
            if (strpos($ts, " ") || strpos($ts, "\n") || strpos($ts, "\t") || strpos($ts, "\r")) {
              $ts = rtrim($ts);
            }
            $ts .= " ";
            $new .= $ts;
            $ot = T_OPEN_TAG;
            $iw = true;
          } elseif ($tn == T_OPEN_TAG_WITH_ECHO) {
            $new .= $ts;
            $ot = T_OPEN_TAG_WITH_ECHO;
            $iw = true;
          } elseif ($tn == T_CLOSE_TAG) {
            if ($ot == T_OPEN_TAG_WITH_ECHO) {
              $new = rtrim($new, "; ");
            } else {
              $ts = " " . $ts;
            }
            $new .= $ts;
            $ot = null;
            $iw = false;
          } elseif (in_array($tn, self::$IW)) {
            $new .= $ts;
            $iw = true;
          } elseif ($tn == T_CONSTANT_ENCAPSED_STRING || $tn == T_ENCAPSED_AND_WHITESPACE) {
            /*            if ($ts[0] == '"') {
              $ts = addcslashes($ts, "\n\t\r"); //VR
              } */
            $new .= $ts;
            $iw = true;
          } elseif ($tn == T_WHITESPACE) {
            $nt = @$tokens[$i + 1];
            if (!$iw && (!is_string($nt) || $nt == '$') && !in_array($nt[0], self::$IW)) {
              $new .= " ";
            }
            $iw = false;
          } elseif ($tn == T_END_HEREDOC) {
            $new .= "$ts;\n"; //VR add newline
            $iw = true;
            for ($j = $i + 1; $j < $c; $j++) {
              if (is_string($tokens[$j]) && $tokens[$j] == ";") {
                $i = $j;
                break;
              } else if ($tokens[$j][0] == T_CLOSE_TAG) {
                break;
              }
            }
          } elseif ($tn == T_COMMENT || $tn == T_DOC_COMMENT) {
            $iw = true;
          } else {
            if ($tn == T_START_HEREDOC && !mb_strpos($ts, "\n")) { //VR check newline
              $ts .= "\n";
            }
            $new .= $ts;
            $iw = false;
            if (!is_null($flg)) {//VR identifier registration
              call_user_func($this->obn, $i, $tokens); //save possible identifier
              $flg = !is_null($ot) || $flg; // mark php content
            }
          }
        }
        $ls = "";
      } else {
        if (($token == ";" || $token == ":") && $ls == $token) {
          $new .= $this->Double($tokens, $i); //VR
        } else {
          $new .= $token;
          $ls = $token;
        }
        $iw = true;
      }
    }
    return $new;
  }

  /**
   * check to omit doubled character
   * @param array $tks tokens
   * @param int $i current token index
   * @return string append to output
   */
  private function Double($tks, $i) {
    $r = '';
    if ($tks[$i] == ';') {
      $j = $i - 1;
      while ($j > 2 && $tks[$j] != '(') { // find condition beginning
        $j--;
      }
      if ($j > 2) {
        if (is_array($tks[$j - 1]) && $tks[$j - 1][0] == T_WHITESPACE) {
          $j--;
        }
        if (is_array($tks[$j - 1]) && $tks[$j - 1][0] == T_FOR) {
          $r = $tks[$i];  // for (;;)
        }
      }
    }
    return $r;
  }

}
