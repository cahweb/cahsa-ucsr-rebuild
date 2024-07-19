<?php
/**
 * Header Snippet
 *
 * The page header, to be included at the top of every page. Contains
 * standard boilerplate and stylesheet information.
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

maybeStartSession();

// Maximum number of hours to maintain a user login session
$hoursMax = 8;

// The user session will time out after 8 hours. This can easily be changed
if (isset($_SESSION['loggedInAt']) && time() - $_SESSION['loggedInAt'] > ($hoursMax * 60 * 60)) {
    ?>
    <meta http-equiv="refresh" content="0;url=logout.php">
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>CAHSA: Undergraduate Course Substitution Request</title>
        <meta name="Author" content="University of Central Florida, College of Arts and Humanities" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <link rel="icon" href="https://ucf.edu/img/pegasus-icon.png" type="image/png" />

        <!-- Athena stuff -->
        <link rel="stylesheet" href="athena/css/framework.min.css" type="text/css" />

        <link rel="stylesheet" href="css/styles.css" type="text/css" />
    </head>
    <body>
        <script type="text/javascript" id="ucfhb-script" src="//universityheader.ucf.edu/bar/js/university-header.js?use-1200-breakpoint=1"></script>
        <header class="container mt-5">
            <div class="row">
                <div class="col-12">
                    <h1 class="text-center h3 font-weight-500 letter-spacing-1 mb-2 text-uppercase">College of Arts &amp; Humanities Student Advising</p>
                </div>
            </div>
        </header>
