<?php
 // vvv DO NOT MODIFY/REMOVE vvv

// check current php version to ensure it meets 2300's requirements
function check_php_version()
{
  if (version_compare(phpversion(), '7.0', '<')) {
    define(VERSION_MESSAGE, "PHP version 7.0 or higher is required for 2300. Make sure you have installed PHP 7 on your computer and have set the correct PHP path in VS Code.");
    echo VERSION_MESSAGE;
    throw VERSION_MESSAGE;
  }
}
check_php_version();

function config_php_errors()
{
  ini_set('display_startup_errors', 1);
  ini_set('display_errors', 0);
  error_reporting(E_ALL);
}
config_php_errors();

// open connection to database
function open_or_init_sqlite_db($db_filename, $init_sql_filename)
{
  if (!file_exists($db_filename)) {
    $db = new PDO('sqlite:' . $db_filename);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (file_exists($init_sql_filename)) {
      $db_init_sql = file_get_contents($init_sql_filename);
      try {
        $result = $db->exec($db_init_sql);
        if ($result) {
          return $db;
        }
      } catch (PDOException $exception) {
        // If we had an error, then the DB did not initialize properly,
        // so let's delete it!
        unlink($db_filename);
        throw $exception;
      }
    } else {
      unlink($db_filename);
    }
  } else {
    $db = new PDO('sqlite:' . $db_filename);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
  }
  return NULL;
}

function exec_sql_query($db, $sql, $params = array())
{
  $query = $db->prepare($sql);
  if ($query and $query->execute($params)) {
    return $query;
  }
  return NULL;
}
// ^^^ DO NOT MODIFY/REMOVE ^^^

$db = open_or_init_sqlite_db("secure/site.sqlite", "secure/init.sql");

###################################################################################################
// Login and logout
###################################################################################################

define('SESSION_COOKIE_DURATION', 60 * 60 * 1);

function login($username, $password)
{
  global $db;
  global $current_user;
  if (isset($username) && isset($password)) {
    // Check if the username exists in users table
    $records = exec_sql_query($db, "SELECT * FROM users WHERE username = :username;", array(':username' => $username))->fetchAll();
    // If records does exist
    if ($records) {
      // Take the user information and store into variable
      $user = $records[0];
      // Check password against hash in DB
      if (password_verify($password, $user['password'])) {
        // Generate random session for user
        $session_id = session_create_id();
        // Store session ID in database for the login user's user_id
        $sql_find_session = "INSERT INTO sessions (user_id, session) VALUES (:user_id, :session);";
        $params_find_session = array(
          ':user_id' => $user['id'],
          ':session' => $session_id
        );
        $store_session = exec_sql_query($db, $sql_find_session, $params_find_session);
        // If session is inserted into database
        if ($store_session) {
          // Create cookie and set time limit for continuous login
          setcookie("session", $session_id, time() + SESSION_COOKIE_DURATION);
          // set $current_user and the user logged in
          $current_user = $user;
          return $current_user;
        }
      }
    }
  }
  // if username and password is not given set $current_user as NULL so that user would not be able to see content.
  $current_user = NULL;
  return NULL;
}
// Code modified from lab08 login function provided by Kyle Harms
// Source: https://github.coecis.cornell.edu/info2300-sp2019/tt426-lab-08/blob/master/includes/init.php


function find_user($user_id)
{
  global $db;
  // Find user from database that corresponds to the $user_id parameter
  $sql_user = "SELECT * from users WHERE id = :user_id;";
  $params_user = array(':user_id' => $user_id);
  $records = exec_sql_query($db, $sql_user, $params_user)->fetchAll();

  // If record does exist return the first index as there would only be one element in the array.
  if ($records) {
    return $records[0];
  }
  // If there are no records return NULL so that program would not crash.
  return NULL;
}

// Code modified from lab08 find_user() section provided by Kyle Harms
// Source: https://github.coecis.cornell.edu/info2300-sp2019/tt426-lab-08/blob/master/includes/init.php

function find_session($session)
{
  global $db;

  // Find session in database
  if (isset($session)){
    $sql = "SELECT * FROM sessions WHERE session = :session;";
    $params = array(':session' => $session);
    $records = exec_sql_query($db, $sql, $params)->fetchAll();

    // if $records does exist return the user_id of the user so that it can be used as a parameter for find_user() function.
    if ($records) {
      $account_id = $records[0];
      return $account_id['user_id'];
      // return $records[0];
    }
  }
  return NULL;
}

// Code modified from lab08 find_session() section provided by Kyle Harms
// Source: https://github.coecis.cornell.edu/info2300-sp2019/tt426-lab-08/blob/master/includes/init.php

function login_check()
{
  global $current_user;

  // If session exists
  if (isset($_COOKIE["session"])) {
    $session = $_COOKIE["session"];
    // Find session from the session database
    $session_record = find_session($session);

    // If cookie exists renew the cookie for one hour
    if (isset($session_record)) {
      // Find the user with the session and assign to a variable.
      $current_user = find_user($session_record);
      // Renew the cookie for one hour because the user is still active.
      setcookie("session", $session, time() + SESSION_COOKIE_DURATION);
      return $current_user;
    }
  }
  // If the cookie doesn't exist set $current_user to NULL so that the user can not see content.
  $current_user = NULL;
  return NULL;
}

// Code modified from lab08 session_login() section provided by Kyle Harms
// Source: https://github.coecis.cornell.edu/info2300-sp2019/tt426-lab-08/blob/master/includes/init.php

function is_user_logged_in()
{
  global $current_user;
  // Return TRUE if current_user is not NULL => shows that user exists.
  return ($current_user != NULL);
}

// Code modified from lab08 login section provided by Kyle Harms
// Source: https://github.coecis.cornell.edu/info2300-sp2019/tt426-lab-08/blob/master/includes/init.php

function log_out()
{
  global $session;
  global $db;
  global $current_user;
  // remove the session from the cookie and make it expire so that user will not be logged in
  setcookie('session', $session, time() - SESSION_COOKIE_DURATION);
  // setcookie('session', '', time() - SESSION_COOKIE_DURATION);

  // Delete session from database associated with user
  $sql_delete_session = "DELETE FROM sessions WHERE user_id = :user_id";
  $params_delete_session = array(":user_id" => $current_user['id']);
  exec_sql_query($db, $sql_delete_session, $params_delete_session);

  // Set $current_user to NULL so that login contents doesn't show
  $current_user = NULL;
}
// Code modified from lab08 log_out() provided by Kyle Harms
// Source: https://github.coecis.cornell.edu/info2300-sp2019/tt426-lab-08/blob/master/includes/init.php

// If use presses login button
if (isset($_POST['login'])) {
  $username = htmlspecialchars(trim($_POST['username']));
  $password = htmlspecialchars(trim($_POST['password']));
  login($username, $password);
} else {
  // Check if already logged in by cookie
  login_check();
}

// Code modified from lab08 login provided by Kyle Harms
// Source: https://github.coecis.cornell.edu/info2300-sp2019/tt426-lab-08/blob/master/includes/init.php

// If user presses logout button and user is logged in, execute log_out() function
if (isset($current_user) && (isset($_GET['logout']))) {
  log_out();
}

// Code modified from lab08 logout provided by Kyle Harms
// Source: https://github.coecis.cornell.edu/info2300-sp2019/tt426-lab-08/blob/master/includes/init.php

##########################################################################################
// You may place any of your code here.
##########################################################################################
$db = open_or_init_sqlite_db("secure/gallery.sqlite", "secure/init.sql");

const UPLOAD_PATH = "uploads/documents/";

$pages = [
  ['index.php', 'Home'], ['login.php', 'Login'], ['logout.php', 'Logout'], ['edit.php', 'Change Gallery'], ['single_image.php', 'Picture']
];

$current_page = basename($_SERVER['PHP_SELF']);

function title()
{
  global $pages, $current_page;
  $title = '';

  foreach ($pages as $element) {
    $file = $element[0];
    $pagename = $element[1];

    if ($current_page == $file) {
      $title = $pagename . ' - ';
      break;
    }
  }

  $title = $title . "Foods Of Ithaca";
  echo htmlspecialchars($title);
}

function page_identify()
{
  global $current_user;
  global $current_page;
  if ((is_user_logged_in() && ($current_page == "index.php")) || (is_user_logged_in() && ($current_page == "single_image.php"))) {
    echo ("<a href =" . "edit.php" . " class=" . "element" . ">Edit</a>");
  } elseif ($current_page == 'index.php' || $current_page == 'single_image.php') {
    echo ("<a href =" . "login.php" . " class=" . "element" . ">Edit Photo Gallery</a>");
  } else {
    if (is_user_logged_in()) {
      $logout_url = 'logout.php' . '?' . http_build_query(array('logout' => ''));
      echo '<a class="element" href="' . $logout_url . '">Sign Out ' . htmlspecialchars($current_user['username']) . '</a>';
    }
  }
}

$messages = array();
$messages_2 = array();

/**
* Adds message to the $message to print out error message
*/
function add_message($message)
{
  global $messages;
  array_push($messages, $message);
}

/**
 * Adds message to the $message_2 to print out error message
 */
function add_message_2($message)
{
  global $messages_2;
  array_push($messages_2, $message);
}

/**
 * Shows message to the user. Used with add_message($message)
 */
function show_message()
{
  global $messages;
  foreach ($messages as $message) {
    echo (htmlspecialchars($message));
  }
}

/**
 * Shows message to the user. Used with add_message($message)
 */
function show_message_2()
{
  global $messages_2;
  foreach ($messages_2 as $message) {
    echo (htmlspecialchars($message));
  }
}

/**
 * Checks if the tag textbox are selected
 */
function validate_tag($array)
{
  $validate = FALSE;
  foreach ($array as $element) {
    if (isset($_POST[$element["id"]]) || isset($_GET[$element["id"]])) {
      $validate = TRUE;
      break;
    }
  }
  return $validate;
}
