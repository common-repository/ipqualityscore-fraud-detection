<tr valign="top" style="border-top:1px solid grey">
	<th scope="row">
		<?php echo $setting['friendly_name']; ?>
	</th>
	<td>
		<?php if($setting['type'] === 'boolean'){ ?>
			<input type="hidden" name="<?php echo $setting['name']; ?>" value="0" />
			<input type="checkbox" value="1" <?php IPQConditions($setting); ?> <?php if(get_option($setting['name']) === '1' || ($setting['default'] === '1' && get_option($setting['name']) !== '0')){ ?>checked="checked"	<?php } ?> />
		<?php } elseif($setting['type'] === 'string' || $setting['type'] === 'url'){ ?>
			<input type="text" <?php IPQConditions($setting); ?> value="<?php echo esc_attr(get_option($setting['name'], isset($setting['default']) ? $setting['default'] : false)); ?>" />
		<?php } elseif($setting['type'] === 'number'){ ?>
		<input type="number" <?php IPQConditions($setting); ?> value="<?php echo esc_attr(get_option($setting['name'], isset($setting['default']) ? $setting['default'] : false)); ?>" />
		<?php } elseif($setting['type'] === 'select'){ ?>
			<select <?php IPQConditions($setting); ?>>
				<?php foreach($setting['options'] as $key => $value){ ?>
					<option value="<?php echo esc_attr($key); ?>" <?php if((string) get_option($setting['name']) === (string) $key){ ?>selected="selected"<?php } ?>><?php echo esc_attr($value); ?></option>
				<?php } ?>
			</select>
		<?php } elseif($setting['type'] === 'multiselect'){ ?>
			<select multiple="multiple" <?php IPQConditions($setting); ?> style="width:400px;height:400px;">
				<?php foreach($setting['options'] as $key => $value){ ?>
					<option value="<?php echo esc_attr($key); ?>" <?php if(IPQOptionJSON(get_option($setting['name']), $key)){ ?>selected="selected"<?php } ?>><?php echo esc_attr($value); ?></option>
				<?php } ?>
			</select>
		<?php } ?>
	</td>
</tr>
<?php if(isset($setting['help_text'])){ ?>
	<tr class="help-text">
		<td colspan="2">
			<?php echo $setting['help_text'] ?>
		</td>
	</tr>
<?php } ?>