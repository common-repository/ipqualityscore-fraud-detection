jQuery(document).ready(function(){
	var Settings = {
		Init: function(){
			Settings.Check();
			
			jQuery('body').on('change', 'input, select', function(){
				Settings.Check();
			});

			jQuery('body').on('click', '.update_settings', function(e){
				TabHandler.Unsaved = false;
				Settings.Check();
				jQuery('body').find('.update_settings_form').submit();
			});
		},
		Check: function(){
			jQuery("input[data-conditions], select[data-conditions]").each(function(){
				var conditions = JSON.parse(jQuery(this).attr('data-conditions'));

				var show = true;
				jQuery.each(conditions, function(key, value){
					if(value === true || value === false){
						if(jQuery('.' + key).is(':checked') !== value){
							show = false;
						}
					} else {
						if(jQuery('.' + key).val() !== value){
							show = false;
						}
					}
				});

				if(show){
					jQuery(this).parents('tr').show();
					jQuery(this).parents('tr').next('.help-text').show();
					jQuery(this).attr('name', jQuery(this).attr('data-name'));
				} else {
					jQuery(this).parents('tr').hide();
					jQuery(this).parents('tr').next('.help-text').hide();
					jQuery(this).attr('name', 'notavailable');
				}
			});
		}
	};
	
	Settings.Init();

	var TabHandler = {
		Unsaved: false,
		Init: function(){
			jQuery('body').on('click', '.nav-tab', function(){
				jQuery('.nav-tab-active').removeClass('nav-tab-active');
				jQuery(this).addClass('nav-tab-active');
				jQuery('.tab-pane.active').removeClass('active');
				jQuery(jQuery(this).attr('href')).addClass('active');
				TabHandler.ClearTabs();
			});

			TabHandler.ClearTabs();
			jQuery('.tab-content').show();

			window.onbeforeunload = function() {
				return TabHandler.Unsaved ? "If you leave this page you will lose your unsaved changes." : null;
			};
			
			jQuery('body').on('change', 'input, select', function(){
				TabHandler.Unsaved = true;
			});
		},
		ClearTabs: function(){
			jQuery('.tab-pane').hide();
			jQuery('.tab-pane.active').show();
		}
	};


	TabHandler.Init();
});