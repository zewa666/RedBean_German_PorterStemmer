<?php
require_once('data.inc');

/**
 * RedBean German Porter Stemmer algorithm as Plugin for RedBeanORM
 *
 * @file    RedBean_German_PorterStemmer.php
 * @desc    Performs stemmed search on given Beantype and property
 * @author  Zewa666
 *
 */
class RedBean_German_PorterStemmer implements RedBean_Plugin
{
  const DE_STEMMER_VOKALE = 'aeiouyäöü';
  const DE_WORT_MUSTER = '/^[a-zßäöü]+$/';
  const DE_LITERAL_MUSTER = '/([^a-zA-ZÄÖÜßäëïöüáéíóúèû])/';

  private $de_stemmer_stopwords;
  private $de_stemmer_exceptions;

  public function __construct() {
    global $stopwords_default;
    global $exceptions_default;
    $this->de_stemmer_stopwords = $stopwords_default;
    $this->de_stemmer_exceptions =  $exceptions_default;
  }


  public function searchStemmed(RedBean_OODBBean $bean, $property, $searchText) {

    $stemmedWords = $this->stem_list($searchText);
    $searchQuery = "";
    $searchParams = array();

    foreach($stemmedWords as $key => $word) {
      $searchQuery .= $property . " LIKE :word" . $key . " OR ";
      $searchParams[":word" . $key] = "%" . $word . "%";
    }
    $searchQuery = substr($searchQuery, 0, -3);

    return R::find($bean->getMeta('type'), $searchQuery, $searchParams);
  }

  /*
  * Function gets as text (parameter) and splits the text into words.
  * Then each word is stemmed and the word together with its stem is
  * stored in an array (hash).
  * As a result the hash is returned and can be used as a lookup table
  * to identify words which transform to the same stem.
  * For details please compare 'search.module-stem.patch'
  */
  public function stem_list(&$text) {
  // watchdog('de_stemmer','de_stemmer_stem_list: ' .  setlocale(LC_ALL, NULL), WATCHDOG_NOTICE);
    // Split words from noise and remove apostrophes
    $words = $this->split_text($text);

    $stem_list = array();
    foreach ($words as $word) {
      $stem_list[$word] = utf8_encode($this->wortstamm(strtolower(utf8_decode($word))));
    }
    return $stem_list;
  }

  private function split_text(&$text) {
    $text = $this->punctuation($text);

    // Split words from noise
    return preg_split(RedBean_German_PorterStemmer::DE_LITERAL_MUSTER, $text, -1, PREG_SPLIT_NO_EMPTY);
  }

  private function punctuation(&$text) {
    return preg_replace('/([a-zA-ZÄÖÜßäëïöüáéíóúèû]{3,})[-_\/](?=[0-9a-zA-ZÄÖÜßäëïöüáéíóúèû])/u','\1 ',$text);
  }


  private function region_n($wort) {
    $r = strcspn($wort, RedBean_German_PorterStemmer::DE_STEMMER_VOKALE);
    return $r + strspn($wort, RedBean_German_PorterStemmer::DE_STEMMER_VOKALE, $r) + 1;
  }

  private function stem_preprocess($wort) {
    $wort = strtolower($wort);
    $wort = str_replace("ß", "ss", $wort);
    // replace ß by ss, and put u and y between vowels into upper case
    $wort = preg_replace(  array(  '/ß/',
            '/(?<=['. RedBean_German_PorterStemmer::DE_STEMMER_VOKALE .'])u(?=['. RedBean_German_PorterStemmer::DE_STEMMER_VOKALE .'])/u',
            '/(?<=['. RedBean_German_PorterStemmer::DE_STEMMER_VOKALE .'])y(?=['. RedBean_German_PorterStemmer::DE_STEMMER_VOKALE .'])/u'
               ),
          array(  'ss', 'U', 'Y'  ),
          $wort
        );

    return $wort;
  }


  private function stem_postprocess($wort) {
    $wort = strtolower($wort);

    if (!$this->ausnahme($wort)) // check for exceptions
    {
      $wort = strtr($wort, array('ä' => 'a', 'á' => 'a',
                                 'ë' => 'e', 'é' => 'e',
                                 'ï' => 'i', 'í' => 'i',
                                 'ö' => 'o', 'ó' => 'o',
                                 'ü' => "u", 'ú' => 'u', 'û' => 'u'
                  ));
    }
    return $wort;
  }


  private function wortstamm($wort) {
    static $local_cache = array();        // holds recent stemming results as word -> stem

    if(array_key_exists($wort, $local_cache)) {
      return $local_cache[$wort];
    }

    // nur deutsche Worte folgen diesen Regeln
    if ( !preg_match(RedBean_German_PorterStemmer::DE_WORT_MUSTER,$wort) )
    return $wort;

    $stamm = $this->stem_preprocess($wort);

    $umlaut = preg_match('/[äöüÄÖÜ]/', $wort);

    /*
      * R1 is the region after the first non-vowel following a vowel,
        or is the null region at the end of the word if there is no such non-vowel.
      * R2 is the region after the first non-vowel following a vowel in R1,
        or is the null region at the end of the word if there is no such non-vowel.
    */

    $l = strlen($stamm);
    $r1 = $this->region_n($stamm);
    $r2 = $r1 == $l  ?  $r1  :  $r1 + $this->region_n(substr($stamm, $r1));
    // unshure about interpreting the following rule:
    // "then R1 is ADJUSTED so that the region before it contains at least 3 letters"
    if ($r1 < 3) {
      $r1 = 3;
    }

    /*  Step 1
      Search for the longest among the following suffixes,
          (a) e   em   en   ern   er   es
          (b) s (preceded by a valid s-ending)
      and delete if in R1.
      (Of course the letter of the valid s-ending is not necessarily in R1)
    */

    if (preg_match('/(e|em|en|ern|er|es)$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r1)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }
    elseif (preg_match('/(?<=(b|d|f|g|h|k|l|m|n|r|t))s$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r1)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }


    /*
      Step 2
      Search for the longest among the following suffixes,
          (a) en   er   est
          (b) st (preceded by a valid st-ending, itself preceded by at least 3 letters)
      and delete if in R1.
    */

    if (preg_match('/(en|er|est)$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r1)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }
    elseif (preg_match('/(?<=(b|d|f|g|h|k|l|m|n|t))st$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r1)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }


    /*
        Step 3: d-suffixes ( see http://snowball.tartarus.org/texts/glossary.html )
        Search for the longest among the following suffixes, and perform the action indicated.
        end   ung
      delete if in R2
      if preceded by ig, delete if in R2 and not preceded by e
        ig   ik   isch
      delete if in R2 and not preceded by e
        lich   heit
      delete if in R2
      if preceded by er or en, delete if in R1
        keit
      delete if in R2
      if preceded by lich or ig, delete if in R2
                                               ^ means R1 ?
    */

    if (preg_match('/(?<=eig)(end|ung)$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r2)) {
      ;
    }
    elseif (preg_match('/(end|ung)$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r2)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }
    elseif (preg_match('/(?<![e])(ig|ik|isch)$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r2)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }
    elseif (preg_match('/(?<=(er|en))(lich|heit)$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r1)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }
    elseif (preg_match('/(lich|heit)$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r2)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }
    elseif (preg_match('/(?<=lich)keit$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r1)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }
    elseif (preg_match('/(?<=ig)keit$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r1)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }
    elseif (preg_match('/keit$/u', $stamm, $hits, PREG_OFFSET_CAPTURE, $r2)) {
      $stamm = substr($stamm, 0, $hits[0][1] - $umlaut);
    }

    $local_cache['wort'] = $stamm;
    return $this->stem_postprocess($stamm);
  }


  private function stoppwort($wort) {
    return in_array($wort, $this->de_stemmer_stopwords);
  }


  private function ausnahme(&$wort) {
    if ( array_key_exists($wort, $this->de_stemmer_exceptions) )
    {
      $wort = $this->de_stemmer_exceptions[$wort];
      return TRUE;
    }
    return FALSE;
  }
}

// add plugin to RedBean facade
R::ext( 'stem', function($input) {
  $stemmer = new RedBean_German_PorterStemmer();
  return $stemmer->stem_list($input);
});

R::ext( 'stemmedSearch', function(RedBean_OODBBean $bean, $property, $searchText) {
  $stemmer = new RedBean_German_PorterStemmer();
  return $stemmer->searchStemmed($bean, $property, $searchText);
});
