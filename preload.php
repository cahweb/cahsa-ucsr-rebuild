<?php
/**
 * Preload Snippet
 *
 * Handles loading the environment variables and including the application
 * functions. Should be included just before includes/header.php on every page.
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

// This is cribbed from a environment loader script I found online,
// though it's pared down to just loading the variables using
// `putenv` and `$_ENV`
require_once "class.dot-env.php";
use CAH\Util\DotEnv;
use CAH\Util\DotEnvException;

$dotEnv = new DotEnv(__DIR__);

require_once "includes/functions.php";
