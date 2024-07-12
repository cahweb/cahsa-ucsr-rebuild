<?php
namespace CAH\CAHSA;
?>
<body style="font-family: sans-serif;">
    <table style="width: 900px; border: none; border-collapse: collapse;">
        <tr>
            <td>
                <p><?= $requestorName ?>,</p>
                <p>
                    The course substitution request for <?= $reqData['name'] ?> (UCF ID: <?= $reqData['pid'] ?>) 
                    <?php if ($requestStatus == RequestStatus::SENT_BACK) : ?>
                    requires additional information:
                    <?php else : ?>
                    was not processed for the following reason:
                    <?php endif; ?>
                </p>
                <blockquote><?= $reason ?></blockquote>
                <p> 
                    <?php if ($requestStatus == RequestStatus::SENT_BACK) : ?>
                    You may <a href="<?= getenv('BASEURL'); ?>/edit.php?id=<?= $reqData['id'] ?>">return to the portal</a> to edit this request and resubmit.
                    <?php endif; ?>
                </p>
                <p>
                    Thank you,<br><br>
                    <span style="font-size: 1.5rem; font-style: bold;">Academic Success Coaching</span><br>
                    College of Arts &amp; Humanities<br>
                    TCH 159 &ndash; <a href="mailto:cahsa@ucf.edu">cahsa@ucf.edu</a><br>
                    <a href="https://cah.ucf.edu/cahsa" target="_blank" rel="noopener">https://cah.ucf.edu/cahsa</a>
                </p>
            </td>
        </tr>
    </table>
</body>
