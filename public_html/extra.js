/*
##
## Copyright 2013-2017 Opera Software AS
##
## Licensed under the Apache License, Version 2.0 (the "License");
## you may not use this file except in compliance with the License.
## You may obtain a copy of the License at
##
## http://www.apache.org/licenses/LICENSE-2.0
##
## Unless required by applicable law or agreed to in writing, software
## distributed under the License is distributed on an "AS IS" BASIS,
## WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
## See the License for the specific language governing permissions and
## limitations under the License.
##
*/

// Handle 'navigate-back' links
$(function() {
	$('a.navigate-back').on('click', function(e) {
		window.history.back();
		event.stopPropagation();
	});
});

// Remember the last-selected tab in a tab group
$(function() {
	if(sessionStorage) {
		$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
			//save the latest tab
			sessionStorage.setItem('lastTab' + location.pathname, $(e.target).attr('href'));
		});

		//go to the latest tab, if it exists:
		var lastTab = sessionStorage.getItem('lastTab' + location.pathname);

		if (lastTab) {
			$('a[href="' + lastTab + '"]').tab('show');
		} else {
			$('a[data-toggle="tab"]:first').tab('show');
		}
	}

	get_tab_from_location();
	window.onpopstate = function(event) {
		get_tab_from_location();
	}
	function get_tab_from_location() {
		// Javascript to enable link to tab
		var url = document.location.toString();
		if(url.match('#')) {
			$('.nav-tabs a[href="#'+url.split('#')[1]+'"]').tab('show');
		}
	}

	// Do the location modifying code after all other setup, since we don't want the initial loading to trigger this
	$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
		if(history) {
			history.replaceState(null, null, e.target.href);
		} else {
			window.location.hash = e.target.hash;
		}
	});
});

// Remember the expanded-state of a collapsible section
$(function() {
	get_section_from_location();
	window.onpopstate = function(event) {
		get_section_from_location();
	}
	function get_section_from_location() {
		// Javascript to enable link to section
		var url = document.location.toString();
		if(url.match('#')) {
			var fragment = url.split('#')[1];
		} else {
			var fragment = '';
		}
		$(".collapse").each(function(){
			if(this.id == fragment) $(this).addClass("in");
			else $(this).removeClass("in");
		});
	}

	// Do the location modifying code after all other setup, since we don't want the initial loading to trigger this
	$('.panel-collapse').on('show.bs.collapse', function (e) {
		if(history) {
			history.replaceState(null, null, '#' + e.target.id);
		} else {
			window.location.hash = e.target.id;
		}
	});

});

// Show only chosen fingerprint hash format in list views
$(function() {
	$('table th.fingerprint').first().each(function() {
		$(this).append(' ');
		var select = $('<select>');
		var options = ['MD5', 'SHA256'];
		for(var i = 0, option; option = options[i]; i++) {
			select.append($('<option>').text(option).val(option));
		}
		if(localStorage) {
			var fingerprint_hash = localStorage.getItem('preferred_fingerprint_hash');
			if(fingerprint_hash) {
				select.val(fingerprint_hash);
			}
		}
		$(this).append(select);
		select.on('change', function() {
			if(this.value == 'SHA256') {
				$('span.fingerprint_md5').hide();
				$('span.fingerprint_sha256').show();
			} else {
				$('span.fingerprint_sha256').hide();
				$('span.fingerprint_md5').show();
			}
			if(localStorage) {
				localStorage.setItem('preferred_fingerprint_hash', this.value);
			}
		});
	});
});

// Add confirmation dialog to all submit buttons with data-confirm attribute
$(function() {
	$('button[type="submit"][data-confirm]').each(function() {
		$(this).on('click', function() { return confirm($(this).data('confirm')); });
	});
});

// Add "clear field" button functionality
$(function() {
	$('button[data-clear]').each(function() {
		$(this).on('click', function() { this.form[$(this).data('clear')].value = ''; });
	});
});

// Home page dynamic add pubkey form
$(function() {
	$('#add_key_button').on('click', function() {
		$('#help').hide().removeClass('hidden');
		$('#add_key_form').hide().removeClass('hidden');
		$('#add_key_form').show('fast');
		$('#add_key_button').hide();
		$('#add_public_key').focus();
	});
	$('#add_key_form button[type=button].btn-info').on('click', function() {
		$('#help').toggle('fast');
	});
	$('#add_key_form button[type=button].btn-default').on('click', function() {
		$('#add_key_form').hide('fast');
		$('#add_key_button').show();
	});
});

// Show/hide appropriate sections of the server settings form
$(function() {
	var form = $('#server_settings');
	form.each(function() {
		$('#authorization.hide').hide().removeClass('hide');
		$('#ldap_access_options.hide').hide().removeClass('hide');
		$("input[name='key_management']", form).on('click', function() {display_relevant_options()});
		$("input[name='authorization']", form).on('click', function() {display_relevant_options()});
		function display_relevant_options() {
			if($("input[name='key_management']:checked").val() == 'keys') {
				$('#authorization').show('fast');
				if($("input[name='authorization']:checked").val() == 'manual') {
					$('#ldap_access_options').hide('fast');
				} else {
					$('#ldap_access_options').show('fast');
				}
			} else {
				$('#authorization').hide('fast');
				$('#ldap_access_options').hide('fast');
			}
		}

		var ao_command_enabled = $("input[name='access_option[command][enabled]']", form);
		var ao_command_value = $("input[name='access_option[command][value]']", form);
		var ao_from_enabled = $("input[name='access_option[from][enabled]']", form);
		var ao_from_value = $("input[name='access_option[from][value]']", form);
		ao_command_enabled.on('click', function() {ao_update_disabled()});
		ao_from_enabled.on('click', function() {ao_update_disabled()});
		ao_update_disabled();
		function ao_update_disabled() {
			ao_command_value.prop('disabled', !ao_command_enabled.prop('checked'));
			ao_command_value.prop('required', ao_command_enabled.prop('checked'));
			ao_from_value.prop('disabled', !ao_from_enabled.prop('checked'));
			ao_from_value.prop('required', ao_from_enabled.prop('checked'));
		}
	});
});

// Enable/disable relevant sections of the access options form
$(function() {
	var form = $('#access_options');
	form.each(function() {
		var ao_command_enabled = $("input[name='access_option[command][enabled]']", form);
		var ao_command_value = $("input[name='access_option[command][value]']", form);
		var ao_from_enabled = $("input[name='access_option[from][enabled]']", form);
		var ao_from_value = $("input[name='access_option[from][value]']", form);
		var ao_noportfwd_enabled = $("input[name='access_option[no-port-forwarding][enabled]']", form);
		var ao_nox11fwd_enabled = $("input[name='access_option[no-X11-forwarding][enabled]']", form);
		var ao_nopty_enabled = $("input[name='access_option[no-pty][enabled]']", form);

		ao_command_enabled.on('click', function() {ao_update_disabled()});
		ao_from_enabled.on('click', function() {ao_update_disabled()});

		$("button[type='button']", form).on('click', function(e) {
			var preset
			if(preset = $(e.target).attr('data-preset')) {
				$('input:checkbox', form).val([]);
				ao_command_value.val('');
				ao_from_value.val('');
				if(preset == 'command' || preset == 'dbbackup') {
					ao_command_enabled.prop('checked', true);
					ao_command_value.focus();
					ao_noportfwd_enabled.prop('checked', true);
					ao_nox11fwd_enabled.prop('checked', true);
					ao_nopty_enabled.prop('checked', true);
				}
				if(preset == 'dbbackup') {
					ao_command_value.val('/usr/bin/innobackupex --slave-info --defaults-file=/etc/mysql/my.cnf /var/tmp');
				}
			}
			ao_update_disabled();
		});
		ao_update_disabled();
		function ao_update_disabled() {
			ao_command_value.prop('disabled', !ao_command_enabled.prop('checked'));
			ao_command_value.prop('required', ao_command_enabled.prop('checked'));
			ao_from_value.prop('disabled', !ao_from_enabled.prop('checked'));
			ao_from_value.prop('required', ao_from_enabled.prop('checked'));
		}
	});
});

// Provide dynamic reassign form on user page
$(function() {
	$('button[data-reassign]').on('click', function() {
		var id = $(this).data('reassign');
		var table = $('#' + id);
		var cell = document.createElement('th');
		var checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		$(checkbox).on('click', function() {$("input[type='checkbox']", table).prop('checked', this.checked)});
		cell.appendChild(checkbox);
		table.children('thead').children('tr').prepend(cell);
		table.children('tbody').children('tr').each(function() {
			var hostname = $(this).children('td:first-child').text().trim();
			var cell = document.createElement('td');
			var checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.name = 'servers[]';
			checkbox.value = hostname;
			cell.appendChild(checkbox);
			$(this).prepend(cell);
		});
		$(this).parent().append('<div class="form-group"><label>Reassign to <input type="text" name="reassign_to" class="form-control"></label></div>');
		$(this).parent().append('<div class="form-group"><button type="submit" name="reassign_servers" class="btn btn-primary">Reassign selected servers</button></div>');
		$(this).remove();
	});
});

// Server sync status
$(function() {
	var status_div = $('#server_sync_status');
	status_div.each(function() {
		if(status_div.data('class')) {
			update_server_sync_status(status_div.data('class'), status_div.data('message'));
			$('span.server_account_sync_status').each(function() {
				update_server_account_sync_status(this.id, $(this).data('class'), $(this).data('message'));
			});
		} else {
			$('span', status_div).addClass('text-warning');
			$('span', status_div).text('Pending');
			$('span.server_account_sync_status').addClass('text-warning');
			$('span.server_account_sync_status').text('Pending');
			var timeout = 1000;
			var max_timeout = 10000;
			get_server_sync_status();
		}
		function get_server_sync_status() {
			var xhr = $.ajax({
				url: window.location.pathname + '/sync_status',
				dataType: 'json'
			});
			xhr.done(function(status) {
				if(status.pending) {
					timeout = Math.min(timeout * 1.5, max_timeout);
					setTimeout(get_server_sync_status, timeout);
				} else {
					var classname;
					if(status.sync_status == 'sync success') classname = 'success';
					if(status.sync_status == 'sync failure') classname = 'danger';
					if(status.sync_status == 'sync warning') classname = 'warning';
					update_server_sync_status(classname, status.last_sync.details);
				}
				$.each(status.accounts, function(index, item) {
					if(!item.pending) {
						var classname;
						var message;
						if(item.sync_status == 'proposed') { classname = 'info'; message = 'Requested'; }
						if(item.sync_status == 'sync success') { classname = 'success'; message = 'Synced'; }
						if(item.sync_status == 'sync failure') { classname = 'danger'; message = 'Failed'; }
						if(item.sync_status == 'sync warning') { classname = 'warning'; message = 'Not synced'; }
						update_server_account_sync_status('server_account_sync_status_' + item.name, classname, message);
					}
				});
			});
		}
		function update_server_sync_status(classname, message) {
			$('span', status_div).removeClass('text-success text-warning text-danger');
			$('span', status_div).addClass('text-' + classname);
			$('span', status_div).text(message);
			if(classname == 'success') {
				$('a', status_div).addClass('hidden');
			} else {
				$('a', status_div).removeClass('hidden');
				if(classname == 'warning') $('a', status_div).prop('href', '/help#sync_warning');
				if(classname == 'danger') $('a', status_div).prop('href', '/help#sync_error');
			}
			$('div.spinner', status_div).remove();
			$('button[name=sync]', status_div).removeClass('invisible');
		}
		function update_server_account_sync_status(id, classname, message) {
			$('#' + id).removeClass('text-success text-warning text-danger');
			$('#' + id).addClass('text-' + classname);
			$('#' + id).text(message);
		}
	});
});

// Server account sync status
$(function() {
	var status_div = $('#server_account_sync_status');
	status_div.each(function() {
		if(status_div.data('class')) {
			update_server_account_sync_status(status_div.data('class'), status_div.data('message'));
		} else {
			$('span', status_div).addClass('text-warning');
			$('span', status_div).text('Pending');
			var timeout = 1000;
			var max_timeout = 10000;
			get_server_account_sync_status();
		}
		function get_server_account_sync_status() {
			var xhr = $.ajax({
				url: window.location.pathname + '/sync_status',
				dataType: 'json'
			});
			xhr.done(function(status) {
				console.debug(status);
				if(status.pending) {
					timeout = Math.min(timeout * 1.5, max_timeout);
					setTimeout(get_server_account_sync_status, timeout);
				} else {
					var classname;
					if(status.sync_status == 'sync success') { classname = 'success'; message = 'Synced'; }
					if(status.sync_status == 'sync failure') { classname = 'danger'; message = 'Failed'; }
					if(status.sync_status == 'sync warning') { classname = 'warning'; message = 'Not synced'; }
					update_server_account_sync_status(classname, message);
				}
			});
		}
		function update_server_account_sync_status(classname, message) {
			$('span', status_div).removeClass('text-success text-warning text-danger');
			$('span', status_div).addClass('text-' + classname);
			$('span', status_div).text(message);
			$('div.spinner', status_div).remove();
		}
	});
});

// Server add form - multiple admin autocomplete
$(function() {
	var server_admin = $('input#server_admin');
	server_admin.each(function() {
		server_admin.on('keydown', function(event) {
			var keycode = (event.keyCode ? event.keyCode : event.which);
			if((keycode == 13 || keycode == 32 || keycode == 188) && $("#server_admin").val() != '') { // Enter, space, comma
				appendAdmin();
				// Reset focus to remove <datalist> autocomplete dialog
				$("#server_admin").blur();
				$("#server_admin").focus();
				return false;
			}
		});
		server_admin.on('blur', function(event) {
			if($("#server_admin").val()) {
				appendAdmin();
			}
		});
		function appendAdmin() {
			if($("#server_admins").val()) {
				$("#server_admins").val($("#server_admins").val() + ', ' + $("#server_admin").val());
			} else {
				$("#server_admins").val($("#server_admin").val());
			}
			$("#server_admin").val("");
			$("#server_admins").removeClass('hidden');
		}
		$('input#server_admins').on('blur', function(event) {
			if(!$("#server_admins").val()) {
				$("#server_admins").addClass('hidden');
			}
		});
		if($("#server_admins").val()) {
			$("#server_admins").removeClass('hidden');
		}
	});
});
