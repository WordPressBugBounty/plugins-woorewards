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
				'title' => __("Export Points from MyRewards", 'woorewards-lite'),
				'text' => __("Select a points and rewards system to export the users points from that system.", 'woorewards-lite'),
				'fields' => array(
					'pool' => array(
						'id'    => 'woorewards-lite' . '_from_pool',
						'type'  => 'lacselect',
						'title' => __("points and rewards system", 'woorewards-lite'),
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
						'title' => __("Export", 'woorewards-lite'),
						'extra' => array(
							'link' => array('ajax' => 'woorewards-lite' . '-export-wr'),
						)
					),
				)
			),
			'export_other' => array(
				'id' 	=> 'wr_export_points_other',
				'icon'	=> 'lws-icon-migration',
				'class'	=> 'half',
				'title' => __("Export from other plugins", 'woorewards-lite'),
				'text'	=> sprintf(
					'%s<br/><strong>%s</strong>',
					__("If you're migrating from another loyalty plugin, you can export the users points from the other plugin and import them into MyRewards.", 'woorewards-lite'),
					__("The other plugin needs to be installed and active for this procedure to work.", 'woorewards-lite')
				),
				'fields' => array(
					'meta' => array(
						'id'    => 'woorewards-lite' . '_from_meta',
						'type'  => 'lacselect',
						'title' => __("Loyalty Plugin or Meta Key", 'woorewards-lite'),
						'extra' => array(
							'class' => 'lws-ignore-confirm',
							'value' => '—',
							'maxwidth' => '400px',
							'allownew' => 'on',
							'source' => $exports->getMethods(),
							'gizmo' => true,
							'tooltips' => __("Do not change that value if you are not sure about what you are doing.", 'woorewards-lite'),
						)
					),
					'arg' => array(
						'id'    => 'woorewards-lite' . '_with_arg',
						'type'  => 'lacselect',
						'title' => __("Some Plugin need extra arguments", 'woorewards-lite'),
						'extra' => array(
							'class' => 'lws-ignore-confirm',
							'value' => '—',
							'allownew' => 'on',
							'maxwidth' => '400px',
							'source' => $exports->getArguments(),
							'gizmo' => true,
							'tooltips' => __("Main purpose is for plugins that support several point pools. If the plugin is not listed here, it does not need an extra argument.", 'woorewards-lite'),
						)
					),
					'export' => array(
						'id'    => 'export-points',
						'type'  => 'button',
						'title' => __("Export", 'woorewards-lite'),
						'extra' => array(
							'link' => array('ajax' => 'woorewards-lite' . '-export-points'),
						)
					),
				)
			),
			'import' => array(
				'id' => 'wr_import_points',
				'icon'	=> 'lws-icon-cloud-download-93',
				'title' => __("Import Points", 'woorewards-lite'),
				'class' => 'half',
				'text'  => implode('<br/>', array(
					__("Select the exported file, then click on «Import».", 'woorewards-lite'),
					__("The Import process does <b>not</b> generate any reward.", 'woorewards-lite'),
				)),
				'fields' => array(
					'round' => array(
						'id'    => 'woorewards-lite' . '_rounding',
						'type'  => 'lacselect',
						'title' => __("Round imported points", 'woorewards-lite'),
						'extra' => array(
							'default' => 'floor',
							'maxwidth' => '400px',
							'mode'	=> 'select',
							'tooltips' => __("MyRewards only support integer points", 'woorewards-lite'),
							'source' => array(
								array('value' => 'floor', 'label' => __("Round fractions down", 'woorewards-lite')),
								array('value' => 'ceil',  'label' => __("Round fractions up", 'woorewards-lite')),
								array('value' => 'half_up', 'label' => __("Round to nearest integer, half way round up", 'woorewards-lite')),
								array('value' => 'half_down', 'label' => __("Round to nearest integer, half way round down", 'woorewards-lite')),
							)
						)
					),
					'multiply' => array(
						'id'    => 'woorewards-lite' . '_multiply',
						'type'  => 'text',
						'title' => __("Multiply imported points by", 'woorewards-lite'),
						'extra' => array(
							'default' => '1',
							'placeholder' => '1',
						)
					),
					'behavior' => array(
						'id'    => 'woorewards-lite' . '_behavior',
						'type'  => 'lacselect',
						'title' => __("Import Mode", 'woorewards-lite'),
						'extra' => array(
							'default' => 'replace',
							'maxwidth' => '400px',
							'mode'	=> 'select',
							'source' => array(
								array('value' => 'replace', 'label' => __("Replace customers points", 'woorewards-lite')),
								array('value' => 'add', 'label' => __("Add points to customers totals", 'woorewards-lite')),
							),
						)
					),
					'default' => array(
						'id'    => 'woorewards-lite' . '_default_pool',
						'type'  => 'lacselect',
						'title' => __("Add points to that points and rewards system", 'woorewards-lite'),
						'extra' => array(
							'maxwidth' => '400px',
							'gizmo'    => true,
							'ajax'     => 'lws_woorewards_pool_list',
						)
					),
					'reason'  => array(
						'id'    => 'woorewards-lite' . '_import_reason',
						'title' => __('History Reason', 'woorewards-lite'),
						'type'  => 'text',
						'extra' => array(
							'noconfirm'   => true,
							'gizmo'       => true,
							'attributes'  => array('autocomplete' => 'off'),
							'placeholder' => _x("Import", "History line", 'woorewards-lite'),
						)
					),
					'file' => array(
						'id'    => 'woorewards-lite' . '_import_file',
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
								__("Import", 'woorewards-lite')
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