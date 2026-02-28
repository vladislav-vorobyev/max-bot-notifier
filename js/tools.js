/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */

/**
 * 
 * Function to initialize handler for make a fold/unfold of structure tree view
 * 
 */
(function($) {
	if (window.print_r_tree_click_handler == undefined) {
		window.print_r_tree_click_handler = function() { $(this).parent().toggleClass('open'); }
		window.print_r_tree_clickp_handler = function() { $(this).parent().find('.a-box').addClass('open'); }
		window.print_r_tree_clickm_handler = function() { $(this).parent().find('.a-box').removeClass('open'); }
		$(document).ready(() => {
			$('.print-r-tree .toggle-a').off('click').on('click', window.print_r_tree_click_handler).addClass('pointer');
			$('.print-r-tree .open-all').off('click').on('click', window.print_r_tree_clickp_handler).addClass('pointer');
			$('.print-r-tree .close-all').off('click').on('click', window.print_r_tree_clickm_handler).addClass('pointer');
		});
	}
})(jQuery);

/**
 * 
 * Function to initialize handler for table structure
 * 
 */
(function($) {
	$(document).ready(() => {
		$('table.db tbody td.json-val').off('click').on('click', function() {
			$(this).toggleClass('pre');
		}).addClass('pointer');
	});
})(jQuery);
