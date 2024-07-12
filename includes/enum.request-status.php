<?php
/**
 * Request Status Pseudo-Enum
 *
 * Since PHP doesn't have native enum support, this is a common
 * workaround. I use this to cut down on the risk of typos when
 * trying to compare or set the status of requests.
 *
 * @category CAH
 * @package  CAHSA
 * @author   Mike W. Leavitt <michael.leavitt@ucf.edu>
 * @version  2.0.0
 */
namespace CAH\CAHSA;

final class RequestStatus
{
    const PENDING       = 'pending';
    const PROCESSED     = 'processed';
    const NOT_PROCESSED = 'not processed';
    const SENT_BACK     = 'sent back';
}