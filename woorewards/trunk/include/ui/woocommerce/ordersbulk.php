<?php
namespace LWS\WOOREWARDS\Ui\Woocommerce;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add an entry in Orders list bluk action.
 *  Allow massive points remove (reset processed flags).
 *  Allow massive points process (reset refund flags).
 */
class OrdersBulk
{
  const ACTION_PROCESS_POINTS = 'lws_wr_process_points';
  const ACTION_RESULT = 'lws_wr_bulk_counts';

  private $points = 0;
  private $processed = array();
  private $checked = 0;

	static function install()
	{
		$me = new self();
    $screens = array('woocommerce_page_wc-orders', 'edit-shop_order');
    foreach ($screens as $screen) {
      \add_filter('bulk_actions-' . $screen, array($me, 'addActions'), 900, 1);
      \add_filter('handle_bulk_actions-' . $screen, array($me, 'handleActions'), 10, 3);
    }
    \add_action('admin_notices', array($me, 'notice'));
	}

  static function getLabel()
  {
    return \apply_filters('lws_woorewards_orderbulk_action_process_points_label', __("Process MyRewards Points", 'woorewards-lite'));
  }

  public function addActions($actions)
  {
    $actions[self::ACTION_PROCESS_POINTS] = self::getLabel();
    return $actions;
  }

  public function handleActions($redirectTo, $action, $postIds)
  {
    $this->resetCounters();
    if (self::ACTION_PROCESS_POINTS === $action) {
      // for stats
      \add_action('lws_woorewards_wc_order_trigger_order_done', array($this, 'addProcessed'), 10, 3);
      \add_filter('lws_woorewards_core_pool_point_add', array($this, 'addPoints'), 10, 2);
      // process past orders
      foreach ($postIds as $postId) {
        $order = \wc_get_order($postId);
        if ($order) {
          \do_action('lws_woorewards_pool_on_order_done', $postId, $order);
          $this->checked++;
        }
      }
      // for stats
      \remove_action('lws_woorewards_wc_order_trigger_order_done', array($this, 'addProcessed'), 10);
      \remove_filter('lws_woorewards_core_pool_point_add', array($this, 'addPoints'), 10);

      return \add_query_arg(array(
        self::ACTION_RESULT => \implode('_', array($this->checked, count($this->processed), $this->points)),
      ), $redirectTo);
    } else {
      return $redirectTo;
    }
  }

  public function addProcessed($action, $order, $pool)
  {
    $orderId = $order->get_id();
    $this->processed[$orderId] = true;
  }

  public function addPoints($value, $userId)
  {
    try{
    	$this->points += $value;
		} catch(\Exception $e){
			$this->points = PHP_INT_MAX ; // overflow
		}
    return $value;
  }

  protected function resetCounters()
  {
    $this->points    = 0;
    $this->processed = array();
    $this->checked   = 0;
  }

  public function notice()
  {
    $count_safe = isset($_REQUEST[self::ACTION_RESULT]) ? \sanitize_key($_REQUEST[self::ACTION_RESULT]) : false;
    if (false !== $count_safe) {
      $counts = \array_map('\intval', \explode('_', $count_safe));
      while (count($counts) < 3)
        $counts[] = 0;
      list($checked, $processed, $points) = $counts;

      if ($checked > 0) {
        $content = sprintf(
          __("<b>%d</b> orders verified, including <b>%d</b> processed for a sum of <b>%d</b> points.", 'woorewards-lite'),
          $checked, $processed, $points
        );
        echo "<div class='notice notice-success lws-wr-order-bulk-action'><p>{$content}</p></div>";
      }
    }
  }
}