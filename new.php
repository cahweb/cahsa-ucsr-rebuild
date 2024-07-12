<?php
/**
 * New Request Page
 *
 * Allows the user to start a new request by entering a student's PID. Mostly
 * just shunts them to edit.php with the student PID as part of `$_POST`, but
 * it allows creating a new request and editing an existing one to use the
 * same form code.
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

require_once "preload.php";
require_once "includes/header.php";

$currentPage = 'new';

// If the user isn't authorized, send them to the login page
if (!$_SESSION['authorized']) {
    ?>
    <meta http-equiv="refresh" content="0;url=login.php?from=<?= $currentPage ?>">
    <?php
} else {
    // Otherwise, include the navigation bar
    include "includes/navbar.php";
}

?>

<?php if ($_SESSION['authorized']) : // Only display the page content at all if the user is authorized ?>
<main class="container mt-5 mb-4">
    <div class="row">
        <h1 class="heading-underline col-sm-9 col-md-8 col-lg-6 col-xl-5 mx-auto mb-5">New Request</h1>
    </div>
    <div class="row mb-3">
        <div class="col-sm-9 col-md-8 col-lg-6 col-xl-5 mx-auto">
            <form id="pid-lookup" action="edit.php" method="post" class="form-inline mx-auto">
                <p class="form-text text-muted"><em>Enter Student PID to begin new request.</em></p>
                <div class="form-group mr-2 mb-2">
                    <label class="sr-only" for="pid">PID</label>
                    <input type="text" class="form-control mr-2" id="pid" name="pid" maxlength="7" placeholder="PID">
                </div>
                <button type="submit" name="pid-lookup" class="btn btn-primary mb-2">Look Up</button>
            </form>
        </div>
    </div>
    <?php if (!empty($messages)) : foreach ($messages as $msg) : ?>
    <div class="alert alert-<?= $msg['level'] ?> alert-dismissable fade show mb-3" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <p><?= $msg['text'] ?></p>
    </div>
    <?php endforeach; endif; ?>
</main>
<?php endif; ?>

<?php
require_once "includes/footer.php";
