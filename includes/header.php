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
        <header class="container-fluid">
            <?php
            $fileName = "";
            $headerImages = [];
            foreach (new \DirectoryIterator('images/slideshow') as $fileInfo) {
                if ($fileInfo->isDot() || $fileInfo->isDir() || $fileInfo->getExtension() != 'jpg') {
                    continue;
                }
                $headerImages[] = $fileInfo->getFilename();
            }
            if (!empty($headerImages)) {
                // Choose a random header image to display
                $index = rand(0, count($headerImages) - 1);
                $fileName = $headerImages[$index];
            }
            ?>
            <div class="row">
                <div class="col-12 mx-auto d-flex justify-content-center w-100" id="header-banner">
                    <div class="flex-1">
                        <a href="https://cah.ucf.edu/cahsa">
                            <img src="images/header.png" class="img-fluid" alt="College of Arts and Humanities Student Advising">
                        </a>
                    </div>
                    <div class="flex-1">
                        <img src="images/slideshow/<?= $fileName ?>" class="img-fluid" alt="CAH students performing scholastic activiies">
                    </div>
                </div>
            </div>
        </header>
