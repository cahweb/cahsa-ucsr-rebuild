<?php
/**
 * Main (Index) Page
 *
 * Landing page once a user has logged in, if they weren't redirected from a
 * different page. Allows access to all the main functions they might need
 * while using the application.
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

require_once "preload.php";
require_once "includes/header.php";

$currentPage = "index";

if (!$_SESSION['authorized']) {
?>
<meta http-equiv="refresh" content="0;url=login.php">
<?php
} else {
    include "includes/navbar.php";
}

if ($_SESSION['authorized']) :
?>

<main class="container mt-5 mb-4">
    <div class="row">
        <h1 class="heading-underline col-sm-9 col-md-8 col-lg-6 col-xl-5 mx-auto mb-5">UCSR Dashboard</h1>
    </div>
    <div class="row">
        <div class="col-sm-9 col-md-8 col-lg-6 col-xl-5 mx-auto mb-3">
            <div class="btn-group-vertical btn-group-lg mx-auto">
                <a href="view.php" class="btn btn-primary mb-2">View/Process Requests</a>
                <a href="new.php" class="btn btn-primary mb-2">Start a New Request</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </div>
</main>

<?php
endif;

require_once "includes/footer.php";
