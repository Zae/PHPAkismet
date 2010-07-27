<?
/**
* Ezra Pool (ezra@tsdme.nl)
*
* @author Ezra Pool
* @version 0.0.1
* @license http://www.opensource.org/licenses/bsd-license.php BSD License
*
* Used some functions and idea's from Alex Potsides Akismet PHP 5 class, http://www.achingbrain.net/
*
*  REQUIREMENTS:
*  You need php 5 and CURL installed.
*
*  USAGE:
*  <code>
*    $akismet = new Akismet( 'aoeu1aoue', 'http://www.example.com/blog/');
*    $akismet->set_comment_author($name);
*    $akismet->set_omment_authorEmail($email);
*    $akismet->set_comment_author_url($url);
*    $akismet->set_comment_content($comment);
*    $akismet->set_permalink('http://www.example.com/blog/alex/someurl/');
*    if($akismet->check_comment()){
*      // store the comment but mark it as spam (in case of a mis-diagnosis)
*    }else{
*      // store the comment normally
*    }
*  </code>
*
*  Optionally you may wish to check if your WordPress API key is valid as in the example below.
* 
* <code>
*   $akismet = new Akismet( 'aoeu1aoue', 'http://www.example.com/blog/');
*   
*   if($akismet->verify_key()) {
*     // api key is okay
*   } else {
*     // api key is invalid
*   }
* </code>
*
*/
class Akismet{  
  /* Verify SSL Cert. */
  public $verifypeer = FALSE;
  /* Set connect timeout. */
  public $connecttimeout = 30;
  /* Set timeout default. */
  public $timeout = 30;
  /* Set the useragent. */
  public $useragent = "PHPAkismet | http://github.com/Zae/PHPAkismet";
  /* Contains the last HTTP status code returned. */
  public $http_code;
  /* Contains the last HTTP headers returned. */
  public $http_info = array();
  /* Contains last http_headers */
  public $http_header = array();
  
  /* Contains the comment */
  public $comment = array();
  
  /* Variables used internally by the class and subclasses */
  protected $api_key;
  protected $site_url;
  
  /* This prevents some potentially sensitive information from being sent accross the wire. */
  protected $ignore = array('HTTP_COOKIE', 
              'HTTP_X_FORWARDED_FOR', 
              'HTTP_X_FORWARDED_HOST', 
              'HTTP_MAX_FORWARDS', 
              'HTTP_X_FORWARDED_SERVER', 
              'REDIRECT_STATUS', 
              'SERVER_PORT', 
              'PATH',
              'DOCUMENT_ROOT',
              'SERVER_ADMIN',
              'QUERY_STRING',
              'PHP_SELF' );
  
  /**
   * Set API URLS
   */
  protected function ApiUrl(){ return 'http://'.$this->api_key.'.rest.akismet.com/1.1/'; }
  protected function VerifyUrl(){ return 'http://rest.akismet.com/1.1/'; }
  
  /**
   * construct Akismet object
   */
  function __construct($api_key, $site_url){
    $this->api_key = $api_key;
    $this->site_url = $site_url;
    
    $this->comment['blog'] = $this->site_url;
    $this->comment['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    if(isset($_SERVER['HTTP_REFERER'])) {
      $this->comment['referrer'] = $_SERVER['HTTP_REFERER'];
    }
    
    /* 
    * This is necessary if the server PHP5 is running on has been set up to run PHP4 and
    * PHP5 concurently and is actually running through a separate proxy al a these instructions:
    * http://www.schlitt.info/applications/blog/archives/83_How_to_run_PHP4_and_PHP_5_parallel.html
    * and http://wiki.coggeshall.org/37.html
    * Otherwise the user_ip appears as the IP address of the PHP4 server passing the requests to the 
    * PHP5 one...
    */
    $this->comment['user_ip'] = $_SERVER['REMOTE_ADDR'] != getenv('SERVER_ADDR') ? $_SERVER['REMOTE_ADDR'] : getenv('HTTP_X_FORWARDED_FOR');
  }
  
  /**
  * Verify the API-key
  */
  public function verify_key(){
    $url = $this->VerifyUrl()."verify-key";
    $response = $this->http_post($url, array("key" => $this->api_key, "blog" => $this->site_url));
    return $response == "valid";
  }
  
  /**
  Check if the comment is spam/ham
  */
  public function comment_check($comment=array()){
    $url = $this->ApiUrl()."comment-check";
    $postfields = array_merge($this->comment, $comment);
    $postfields = $this->merge_server_globals($postfields);
    
    $response = $this->http_post($url, $postfields);
    
    if($response == "invalid" && !$this->verify_key()){
      throw new Exception('The Wordpress API key passed to the Akismet constructor is invalid.  Please obtain a valid one from http://wordpress.com/api-keys/');
    }
    
    return $response == "true";
  }
  
  /**
  Submit the comment as spam to the akismet service
  */
  public function submit_spam($comment=array()){
    $url = $this->ApiUrl()."submit-spam";
    $postfields = array_merge($this->comment, $comment);
    $postfields = $this->merge_server_globals($postfields);
    
    $response = $this->http_post($url, $postfields);
    if($response == "invalid" && !$this->verify_key()){
      throw new Exception('The Wordpress API key passed to the Akismet constructor is invalid.  Please obtain a valid one from http://wordpress.com/api-keys/');
    }
    return true;
  }
  
  /**
  Submit the comment as ham to the akismet service
  */
  public function submit_ham($comment=array()){
    $url = $this->ApiUrl()."submit-ham";
    $this->comment = array_merge($this->comment, $comment);
    $postfields = $this->merge_server_globals($postfields);
    
    $response = $this->http_post($url, $postfields);
    
    if($response == "invalid" && !$this->verify_key()){
      throw new Exception('The Wordpress API key passed to the Akismet constructor is invalid.  Please obtain a valid one from http://wordpress.com/api-keys/');
    }
    return true;
  }
  
  /**
  * The IP of the user
  *
  * @param string $userip
  */
  public function set_user_ip($userip) {
    $this->comment['user_ip'] = $userip;
  }
  
  /**
  * The referrer
  *
  * @param string $referrer
  */
  public function set_referrer($referrer) {
    $this->comment['referrer'] = $referrer;
  }
  
  /**
  * A permanent URL referencing the blog post the comment was submitted to.
  *
  * @param string $permalink
  */
  public function set_permalink($permalink) {
    $this->comment['permalink'] = $permalink;
  }
  
  /**
  * The type of comment being submitted.  
  *
  * May be blank, comment, trackback, pingback, or a made up value like "registration" or "wiki".
  */
  public function set_comment_type($commentType) {
    $this->comment['comment_type'] = $commentType;
  }
  
  /**
  * The name that the author submitted with the comment.
  */
  public function set_comment_author($commentAuthor) {
    $this->comment['comment_author'] = $commentAuthor;
  }
  
  /**
  * The email address that the author submitted with the comment.
  *
  */
  public function set_comment_author_email($authorEmail) {
    $this->comment['comment_author_email'] = $authorEmail;
  }
  
  /**
  * The URL that the author submitted with the comment.
  */
  public function set_comment_author_url($authorURL) {
    $this->comment['comment_author_url'] = $authorURL;
  }
  
  /**
  * The comment's body text.
  */
  public function set_comment_content($commentBody) {
    $this->comment['comment_content'] = $commentBody;
  }
  
  /**
   * Make an HTTP request
   *
   * @return API results
   */
  private function http_post($url, $postfields=NULL){
    $this->http_info = array();
    $handle = curl_init();
    /* Curl settings */
    curl_setopt($handle, CURLOPT_HEADER, FALSE);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($handle, CURLOPT_HTTPHEADER, array('Expect:'));
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $this->verifypeer);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
    curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
    curl_setopt($handle, CURLOPT_USERAGENT, $this->useragent);
    curl_setopt($handle, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
    
    curl_setopt($handle, CURLOPT_POST, TRUE);
    if (!empty($postfields)) {
      curl_setopt($handle, CURLOPT_POSTFIELDS, $postfields);
    }
    
    curl_setopt($handle, CURLOPT_URL, $url);
    $response = curl_exec($handle);
    $this->http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $this->http_info = array_merge($this->http_info, curl_getinfo($handle));
    $this->url = $url;
    curl_close($handle);
    return $response;
  }
  
  /**
   * Get the header info to store.
   */
  private function getHeader($ch, $header) {
    $i = strpos($header, ':');
    if (!empty($i)) {
      $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
      $value = trim(substr($header, $i + 2));
      $this->http_header[$key] = $value;
    }
    return strlen($header);
  }
  
  private function merge_server_globals($array, $ignore_list=array()){
    $ignore_list = array_merge($this->ignore, $ignore_list);
    
    foreach($_SERVER as $key => $value) {
      if(!in_array($key, $ignore_list)) {
        if($key == 'REMOTE_ADDR') {
          $array[$key] = $this->comment['user_ip'];
        } else {
          $array[$key] = $value;
        }
      }
    }
    
    return $array;
  }
}
?>