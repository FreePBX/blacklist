function getGrid() {
	return $('#blGrid');
}
function getAddNumber() {
	return $('#addNumber');
}

getAddNumber().on('show.bs.modal', function (e) {
	var number = $(e.relatedTarget).data('number');
	var description = $(e.relatedTarget).data('description');
	var blockedType = $(e.relatedTarget).data('blockedtype');
	$("#number").val(number);
	$("#oldval").val(number);
	$("#description").val(description);
	if(typeof blockedType === 'undefined') {
		var id = (checkidExits('blockedtype_Both')) ? document.getElementById('blockedtype_Both').checked = true : '';
	} else {
		var id = (checkidExits('blockedtype_Both')) ? document.getElementById('blockedtype_'+blockedType).checked = true : '';
	}
});
function checkidExits (id) {
	var idExits = document.getElementById(id);
	if(idExits){
		return true;
	} else{
		return false;
	}
}
$(".destdropdown ").after("<br />");
$(document).on('show.bs.tab', 'a[data-toggle="tab"]', function (e) {
    var clicked = $(this).attr('href');
    switch(clicked){
		case '#settings':
			$('#action-bar').removeClass('hidden');
			$('#Submit').removeClass('hidden');
			$('#Reset').removeClass('hidden');
		break;
		case '#importexport':
			$('#action-bar').removeClass('hidden');
			$('#Submit').addClass('hidden');
			$('#Reset').addClass('hidden');
		break;
		default:
			$('#action-bar').addClass('hidden');
		break;
	}
});
$('#action-bar').addClass('hidden');

$('#submitnumber').on('click', function() {
	var num = $('#number').val();
	var desc = $('#description').val();
	var oldv = $('#oldval').val();
	var blockType = $('input[name="blockedType"]:checked').val();
	$this = this;
	if(num === ''){
		warnInvalid($('#number'), _('Number/CallerID cannot be blank'));
		return;
	}
	if(desc === '1'){
		warnInvalid($('#description'), _('Description not allowed, reserved character!'));
		return;
	}
	$(this).blur();
	$(this).prop("disabled",true);
	$(this).text(_("Adding..."));

	var post_data = {
		module		: 'blacklist',
		command		: 'add',
		action		: "add",
		oldval		: oldv,
		number		: num,
		description	: desc,
		blockType       : blockType
	};
	$.post(window.FreePBX.ajaxurl, post_data, function(data)
	{
		$($this).prop("disabled",false);
		$($this).text(_("Save Changes"));
		if(data.status) {
			if(oldv.length > 0){
				fpbxToast(_("Entry Updated"), '', 'success');
			} else {
				fpbxToast(sprintf(_("Added %s to the blacklist"), num), '', 'success');
			}
			getGrid().bootstrapTable('refresh',{});
			getAddNumber().modal('hide');
		} else {
			fpbxToast(data.message, '', 'error');
		}
	});
});

var processing = null;
$(document).on('click', '[id^="del"]', function() {
	var num = $(this).data('number');
	fpbxConfirm(
		sprintf(_("Are you sure you want to delete the number %s?"), num),
		_("Yes"),_("No"),
		function() {
			var post_data = {
				module	: 'blacklist',
				command	: 'del',
				action	: "delete",
				number	: num,
			};
			$.post(window.FreePBX.ajaxurl, post_data)
			.done(function(data) {
				if (data.status == true) {
					getGrid().bootstrapTable('refresh',{silent: true});
					fpbxToast(sprintf(_('Number %s removed from the blacklist'), num), '', 'success');
				} else {
					fpbxToast(data.message, '', 'error');
				}
			});
		}
	);
});

$(document).on('click', '[id^="report"]', function() {
	var num = $(this).data('number');
	$("#blReport").bootstrapTable('refresh', {
		url: window.FreePBX.ajaxurl + "?module=blacklist&command=calllog&number=" + num ,
	});
	$("#smsReport").bootstrapTable('refresh', {
		url: window.FreePBX.ajaxurl + "?module=blacklist&command=smslog&number=" + num ,
	});
	$("#numreport").modal("show");
});

//Bulk Actions
$('#action-toggle-all').on("change", function() {
	var tval = $(this).prop('checked');
	$('input[id^="actonthis"]').each(function() {
		$(this).prop('checked', tval);
	});
});

$('input[id^="actonthis"],#action-toggle-all').change(function() {
	if($('input[id^="actonthis"]').is(":checked")) {
		$("#trashchecked").removeClass("hidden");
	} else {
		$("#trashchecked").addClass("hidden");
	}

});

//This does the bulk delete...
$("#blkDelete").on("click", function(e) {
	e.preventDefault();
	var numbers = [];
	$('input[name="btSelectItem"]:checked').each(function() {
		var idx = $(this).data('index');
		numbers.push(cbrows[idx]);
	});
	if (numbers.length == 0) {
		fpbxToast('There is no record selected!', '', 'warning');
	} else {
		fpbxConfirm(
			_("Are you sure to delete the selected records?"),
			_("Yes"),_("No"),
			function() {
				getGrid().bootstrapTable('showLoading');
				var post_data = {
					module	: 'blacklist',
					command	: 'bulkdelete',
					numbers	: JSON.stringify(numbers),
				};
				$.post(window.FreePBX.ajaxurl, post_data)
				.done(function() {
					numbers = null;
					getGrid().bootstrapTable('refresh');
					getGrid().bootstrapTable('hideLoading');
				});
		
				//Reset ui elements
				//hide the action element in botnav
				$("#delchecked").addClass("hidden");
				//no boxes should be checked but if they are uncheck em.
				$('input[name="btSelectItem"]:checked').each(function() {
					$(this).prop('checked', false);
				});
				//Uncheck the "check all" box
				$('#action-toggle-all').prop('checked', false);
			}
		);
	}
});


var cbrows = [];
function cbFormatter(val,row,i){
	cbrows[i] = row['number'];
}

function linkFormatter(value,row,idx){
	var html = sprintf('<a href="#" data-toggle="modal" data-target="#addNumber" data-number="%(number)s" data-blockedtype="%(blockedType)s" data-description="%(description)s"><i class="fa fa-pencil"></i></a>', row);
	html += sprintf('&nbsp;<a href="#" id="del%(args[0].number)s" data-idx="%(args[1])s" data-number="%(args[0].number)s" ><i class="fa fa-trash"></i></a>', {args: [row, idx]});
	html += sprintf('&nbsp;<a href="#" id="report%(number)s" data-number="%(number)s"><i class="fa fa-area-chart"></i></a>', row);
	return html;
}
function descFormatter(value,row){
	if(value == 1){
		return "";
	}else{
		return row['description'];
	}
}
function lastDateFormatter (value, row, idx) {
	if (row.count === 0) {
		return value;
	}
	return moment(value).tz(timezone).format(datetimeformat) + " - " + moment(value).tz(timezone).fromNow();
}