<div class="wrap shopp">
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>
	<div class="icon32"></div>
	<h2><?php _e('System Settings','Shopp'); ?></h2>

	<!-- shopp_storage_engine_settings -->
	<?php do_action('shopp_storage_engine_settings'); ?>

	<form name="settings" id="system" action="<?php echo esc_url($this->url); ?>" method="post">
		<?php wp_nonce_field('shopp-settings-system'); ?>

		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><label for="image-storage"><?php _e('Image Storage','Shopp'); ?></label></th>
				<td><select name="settings[image_storage]" id="image-storage">
					<?php echo menuoptions($storage,shopp_setting('image_storage'),true); ?>
					</select><input type="submit" name="image-settings" value="<?php _e('Settings&hellip;','Shopp'); ?>" class="button-secondary hide-if-js"/>
					<div id="image-storage-engine" class="storage-settings"><?php if ($ImageStorage) echo $ImageStorage->ui('image'); ?></div>
	            </td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="download-storage"><?php _e('Product File Storage','Shopp'); ?></label></th>
				<td><select name="settings[product_storage]" id="download-storage">
					<?php echo menuoptions($storage,shopp_setting('product_storage'),true); ?>
					</select><input type="submit" name="download-settings" value="<?php _e('Settings&hellip;','Shopp'); ?>" class="button-secondary hide-if-js"/>
					<div id="download-storage-engine" class="storage-settings"><?php if ($DownloadStorage) echo $DownloadStorage->ui('download'); ?></div>
	            </td>
			</tr>

			<tr>
				<th scope="row" valign="top"><label for="rebuild-index"><?php _e('Search Index','Shopp'); ?></label></th>
				<td><button type="button" id="rebuild-index" name="rebuild" class="button-secondary"><?php _e('Rebuild Product Search Index','Shopp'); ?></button><br />
	            <?php _e('Update search indexes for all the products in the catalog.','Shopp'); ?></td>
			</tr>

			<tr>
				<th scope="row" valign="top"><label for="image-cache"><?php _e('Image Cache','Shopp'); ?></label></th>
				<td><button type="submit" id="image-cache" name="rebuild" value="true" class="button-secondary"><?php _e('Delete Cached Images','Shopp'); ?></button><br />
	            <?php _e('Removes all cached images so that they will be recreated.','Shopp'); ?></td>
			</tr>

			<tr>
				<th scope="row" valign="top"><label for="uploader-toggle"><?php _e('Upload System','Shopp'); ?></label></th>
				<td><input type="hidden" name="settings[uploader_pref]" value="browser" /><input type="checkbox" name="settings[uploader_pref]" value="flash" id="uploader-toggle"<?php if (shopp_setting('uploader_pref') == "flash") echo ' checked="checked"'?> /><label for="uploader-toggle"> <?php _e('Enable Flash-based uploading','Shopp'); ?></label><br />
	            <?php _e('Enable to use Adobe Flash uploads for accurate upload progress. Disable this setting if you are having problems uploading.','Shopp'); ?></td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="script-server"><?php _e('Script Loading','Shopp'); ?></label></th>
				<td><input type="hidden" name="settings[script_server]" value="script" /><input type="checkbox" name="settings[script_server]" value="plugin" id="script-server"<?php if (shopp_setting('script_server') == "plugin") echo ' checked="checked"'?> /><label for="script-server"> <?php _e('Load behavioral scripts through WordPress','Shopp'); ?></label><br />
	            <?php _e('Enable this setting when experiencing problems loading scripts with the Shopp Script Server','Shopp'); ?>
				<div><input type="hidden" name="settings[script_loading]" value="catalog" /><input type="checkbox" name="settings[script_loading]" value="global" id="script-loading"<?php if (shopp_setting('script_loading') == "global") echo ' checked="checked"'?> /><label for="script-loading"> <?php _e('Enable Shopp behavioral scripts site-wide','Shopp'); ?></label><br />
	            <?php _e('Enable this to make Shopp behaviors available across all of your WordPress posts and pages.','Shopp'); ?></div>

	</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="error-notifications"><?php _e('Error Notifications','Shopp'); ?></label></th>
				<td><ul id="error_notify">
					<?php foreach ($notification_errors as $id => $level): ?>
						<li><input type="checkbox" name="settings[error_notifications][]" id="error-notification-<?php echo $id; ?>" value="<?php echo $id; ?>"<?php if (in_array($id,$notifications)) echo ' checked="checked"'; ?>/><label for="error-notification-<?php echo $id; ?>"> <?php echo $level; ?></label></li>
					<?php endforeach; ?>
					</ul>
					<label for="error-notifications"><?php _e("Send email notifications of the selected errors to the merchant's email address.","Shopp"); ?></label>
	            </td>
			</tr>
			<tr>
				<th scope="row" valign="top"><label for="error-logging"><?php _e('Logging','Shopp'); ?></label></th>
				<td><select name="settings[error_logging]" id="error-logging">
					<?php echo menuoptions($errorlog_levels,shopp_setting('error_logging'),true); ?>
					</select><br />
					<label for="error-notifications"><?php _e("Limit logging errors up to the level of the selected error type.","Shopp"); ?></label>
	            </td>
			</tr>
			<?php if (count(ShoppErrorLogging()->tail(2)) > 1): ?>
			<tr>
				<th scope="row" valign="top"></th>
				<td id="errorlog">

				<iframe id="logviewer" src="<?php echo wp_nonce_url(add_query_arg(array('action'=>'shopp_debuglog'),admin_url('admin-ajax.php')),'wp_ajax_shopp_debuglog'); ?>#bottom">
				<p>Loading log file...</p>
				</iframe>

				<p class="alignright"><button name="resetlog" id="resetlog" value="resetlog" class="button"><small><?php _e('Reset Log','Shopp'); ?></small></button></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes','Shopp'); ?>" /></p>
	</form>
</div>

<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($) {

	$('#errorlog').scrollTop($('#errorlog').attr('scrollHeight'));

	$.fn.storageEngineSettings = function (menu,context) {
		var $this = $(menu),
			selected = $this.val(),
			engine = (engines[selected]?engines[selected]:false),
			settings = {context:context},
			container = $('#'+context+'-storage-engine');
			if (storageset != null && storageset[selected] != undefined) {
				$.each(storageset[selected],function (name,setting) {
					settings[name] = setting[context];
				});
			}

			$.tmpl(engine,settings).appendTo(container);
			$(window).scrollTop(0);

			return $this;
	};

	var progressbar = false,
		search_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_rebuild_search_index'); ?>';
		searchprog_url = '<?php echo wp_nonce_url(admin_url('admin-ajax.php'),'wp_ajax_shopp_rebuild_search_index_progress'); ?>',
		engines = <?php echo json_encode($engines); ?>,
		storageset = <?php echo json_encode($storageset); ?>,

		templates = $.each(engines,function (id,engine) {
			$.template(engine,$('#'+engine+'-editor'));
		}),
		imgsmenu = $('#image-storage').change(function () {
			$(this).storageEngineSettings(this,'image');
		}).change(),
		dlsmenu = $('#download-storage').change(function () {
			$(this).storageEngineSettings(this,'download');
		}).change();

	function progress () {
		$.ajax({url:searchprog_url+'&action=shopp_rebuild_search_index_progress',
			type:"GET",
			timeout:500,
			dataType:'text',
			success:function (results) {
				var p = results.split(':'),
					width = Math.ceil((p[0]/p[1])*76);
				if (p[0] < p[1]) setTimeout(progress,1000);
				progressbar.animate({'width':width+'px'},500);
			}
		});

	}

	$('#rebuild-index').click(function () {
		$.fn.colorbox({'title':'<?php _e('Product Indexing','Shopp'); ?>',
			'innerWidth':'250',
			'innerHeight':'50',
			'html':
			'<div id="progress"><div class="bar"><\/div><div class="gloss"><\/div><\/div><iframe id="process" width="0" height="0" src="'+search_url+'&action=shopp_rebuild_search_index"><\/iframe>',
			'onComplete':function () {
				progressbar = $('#progress div.bar');
				progress();
				$('#process').load(function () {
					progressbar.animate({'width':'100%'},500,'swing',function () {
						setTimeout($.fn.colorbox.close,1000);
					});
				});
			}
		});
	});

});
/* ]]> */
</script>