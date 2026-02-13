<?php
namespace LWS\WOOREWARDS\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class PointsManagement
{
	static function mergeGroups(&$groups)
	{
		$groups = array_merge($groups, self::getGroups());
	}

	static function getGroups()
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/pointsflow/exportmethods.php';
		$exports = new \LWS\WOOREWARDS\PointsFlow\ExportMethods();

		$groups = array(
			'export_wr' => array(
				'id' => 'wr_export_points_wr',
				'icon'	 => 'lws-icon-migration',
				'class'	=> 'half',
				'title' => __("Export Points from MyRewards", 'woorewards'),
				'text' => __("Select a points and rewards system to export the users points from that system.", 'woorewards'),
				'fields' => array(
					'pool' => array(
						'id'    => 'woorewards' . '_from_pool',
						'type'  => 'lacselect',
						'title' => __("points and rewards system", 'woorewards'),
						'extra' => array(
							'class'    => 'lws-ignore-confirm',
							'maxwidth' => '400px',
							'gizmo'    => true,
							'ajax'     => 'lws_woorewards_pool_list',
						)
					),
					'export' => array(
						'id'    => 'export-wr',
						'type'  => 'button',
						'title' => __("Export", 'woorewards'),
						'extra' => array(
							'link' => array('ajax' => 'woorewards' . '-export-wr'),
						)
					),
				)
			),
			'export_other' => array(
				'id' 	=> 'wr_export_points_other',
				'icon'	=> 'lws-icon-migration',
				'class'	=> 'half',
				'title' => __("Export from other plugins", 'woorewards'),
				'text'	=> sprintf(
					'%s<br/><strong>%s</strong>',
					__("If you're migrating from another loyalty plugin, you can export the users points from the other plugin and import them into MyRewards.", 'woorewards'),
					__("The other plugin needs to be installed and active for this procedure to work.", 'woorewards')
				),
				'fields' => array(
					'meta' => array(
						'id'    => 'woorewards' . '_from_meta',
						'type'  => 'lacselect',
						'title' => __("Loyalty Plugin or Meta Key", 'woorewards'),
						'extra' => array(
							'class' => 'lws-ignore-confirm',
							'value' => '—',
							'maxwidth' => '400px',
							'allownew' => 'on',
							'source' => $exports->getMethods(),
							'gizmo' => true,
							'tooltips' => __("Do not change that value if you are not sure about what you are doing.", 'woorewards'),
						)
					),
					'arg' => array(
						'id'    => 'woorewards' . '_with_arg',
						'type'  => 'lacselect',
						'title' => __("Some Plugin need extra arguments", 'woorewards'),
						'extra' => array(
							'class' => 'lws-ignore-confirm',
							'value' => '—',
							'allownew' => 'on',
							'maxwidth' => '400px',
							'source' => $exports->getArguments(),
							'gizmo' => true,
							'tooltips' => __("Main purpose is for plugins that support several point pools. If the plugin is not listed here, it does not need an extra argument.", 'woorewards'),
						)
					),
					'export' => array(
						'id'    => 'export-points',
						'type'  => 'button',
						'title' => __("Export", 'woorewards'),
						'extra' => array(
							'link' => array('ajax' => 'woorewards' . '-export-points'),
						)
					),
				)
			),
			'import' => array(
				'id' => 'wr_import_points',
				'icon'	=> 'lws-icon-cloud-download-93',
				'title' => __("Import Points", 'woorewards'),
				'class' => 'half',
				'text'  => implode('<br/>', array(
					__("Select the exported file, then click on «Import».", 'woorewards'),
					__("The Import process does <b>not</b> generate any reward.", 'woorewards'),
				)),
				'fields' => array(
					'round' => array(
						'id'    => 'woorewards' . '_rounding',
						'type'  => 'lacselect',
						'title' => __("Round imported points", 'woorewards'),
						'extra' => array(
							'default' => 'floor',
							'maxwidth' => '400px',
							'mode'	=> 'select',
							'tooltips' => __("MyRewards only support integer points", 'woorewards'),
							'source' => array(
								array('value' => 'floor', 'label' => __("Round fractions down", 'woorewards')),
								array('value' => 'ceil',  'label' => __("Round fractions up", 'woorewards')),
								array('value' => 'half_up', 'label' => __("Round to nearest integer, half way round up", 'woorewards')),
								array('value' => 'half_down', 'label' => __("Round to nearest integer, half way round down", 'woorewards')),
							)
						)
					),
					'multiply' => array(
						'id'    => 'woorewards' . '_multiply',
						'type'  => 'text',
						'title' => __("Multiply imported points by", 'woorewards'),
						'extra' => array(
							'default' => '1',
							'placeholder' => '1',
						)
					),
					'behavior' => array(
						'id'    => 'woorewards' . '_behavior',
						'type'  => 'lacselect',
						'title' => __("Import Mode", 'woorewards'),
						'extra' => array(
							'default' => 'replace',
							'maxwidth' => '400px',
							'mode'	=> 'select',
							'source' => array(
								array('value' => 'replace', 'label' => __("Replace customers points", 'woorewards')),
								array('value' => 'add', 'label' => __("Add points to customers totals", 'woorewards')),
							),
						)
					),
					'default' => array(
						'id'    => 'woorewards' . '_default_pool',
						'type'  => 'lacselect',
						'title' => __("Add points to that points and rewards system", 'woorewards'),
						'extra' => array(
							'maxwidth' => '400px',
							'gizmo'    => true,
							'ajax'     => 'lws_woorewards_pool_list',
						)
					),
					'reason'  => array(
						'id'    => 'woorewards' . '_import_reason',
						'title' => __('History Reason', 'woorewards'),
						'type'  => 'text',
						'extra' => array(
							'noconfirm'   => true,
							'gizmo'       => true,
							'attributes'  => array('autocomplete' => 'off'),
							'placeholder' => _x("Import", "History line", 'woorewards'),
						)
					),
					'file' => array(
						'id'    => 'woorewards' . '_import_file',
						'type'  => 'input',
						'extra' => array(
							'value' => '',
							'placeholder' => '*.json',
							'type' => 'file',
						)
					),
					'import' => array(
						'id'    => 'import-points',
						'type'  => 'custom',
						'title' => '',
						'extra' => array(
							'gizmo'   => true,
							'content' => sprintf(
								'<button type="submit" name="lws_wre_points_action" value="import" class="lws-adm-btn">%s</button>',
								__("Import", 'woorewards')
							)
						)
					),
				)
			),
		);

		if (!\class_exists('\LWS\WOOREWARDS\PRO\Core\Pool')) {
			// get the prefab pool and set it as the only one
			$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, array(
				'showall' => true,
				'force'   => true,
			));
			if ($pools && $pools->count()) {
				$source = $pools->map(function($p) {
					return array('value' => $p->getId(), 'label' => $p->getOption('display_title'));
				});
				if ($pool = $pools->last())
					$value = $pool->getId();
			} else {
				$source = array(
					array('value' => '', 'label' => ''),
				);
				$value = '';
			}
			$groups['export_wr']['fields']['pool']['extra'] = array(
				'class'    => 'lws-ignore-confirm',
				'maxwidth' => '400px',
				'gizmo'    => true,
				'source'   => $source,
				'value'    => $value,
			);
			$groups['import']['fields']['default']['extra'] = array(
				'maxwidth' => '400px',
				'gizmo'    => true,
				'source'   => $source,
				'value'    => $value,
			);
		}

		return $groups;
	}
}