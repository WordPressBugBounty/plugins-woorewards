<?php
/** Register all event and unlockables */
if( !defined('LWS_WOOREWARDS_INCLUDES') ) exit();

require_once LWS_WOOREWARDS_INCLUDES . '/events/t_sponsorshiporigin.php';

\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\Unlockables\Coupon',     LWS_WOOREWARDS_INCLUDES.'/unlockables/coupon.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\FirstOrder',           LWS_WOOREWARDS_INCLUDES.'/events/firstorder.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\OrderAmount',          LWS_WOOREWARDS_INCLUDES.'/events/orderamount.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\OrderCompleted',       LWS_WOOREWARDS_INCLUDES.'/events/ordercompleted.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\ProductReview',        LWS_WOOREWARDS_INCLUDES.'/events/productreview.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\SponsoredOrderAmount', LWS_WOOREWARDS_INCLUDES.'/events/sponsoredorderamount.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\SponsoredOrder',       LWS_WOOREWARDS_INCLUDES.'/events/sponsoredorder.php');