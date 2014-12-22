$('#addNumber').on('show.bs.modal', function (e) {
	var number = $(e.relatedTarget).data('number');
	var description = $(e.relatedTarget).data('description');
	$(e.currentTarget).find('input[name="number"]').val(number);
	$(e.currentTarget).find('input[name="oldval"]').val(number);
	$(e.currentTarget).find('input[name="description"]').val(description);
});

$(document).on('show.bs.tab', 'a[data-toggle="tab"]', function (e) {
    var clicked = $(this).attr('href');
    switch(clicked){
		case '#settings':
			$('#Upload').addClass('hidden');			
			$('#Submit').removeClass('hidden');
			$('#Reset').removeClass('hidden');
		break;
		case '#importexport':
			$('#Submit').addClass('hidden');
			$('#Reset').addClass('hidden');
			$('#Upload').removeClass('hidden');
		break;
		default:
			$('#Submit').addClass('hidden');
			$('#Reset').addClass('hidden');
			$('#Upload').addClass('hidden');
		break;
	}
})

$('#submitnumber').on('click',function(){
	var num = $('#number').val();
	var desc = $('#description').val();
	var oldv = $('#oldval').val();
	if(num == ''){
		warnInvalid($('#number'), 'This cannot be blank');
	}
	$.post("config.php?display=blacklist",
		{
			action : "add",
			oldval : oldv,
			number : num,
			description: desc
		},
		function(data,status){
			if(status == "success"){
				alert(num + _(" Added to the blacklist."));
				$.get( "config.php?display=blacklist&view=grid&fw_popover=1", function( data ) {
					$("#blacklist").html(data);
				});
			}
		}
	);
});

$('[id^="del"]').on('click', function(){
	num = $(this).data('number');
	$.post("config.php?display=blacklist",
		{
			action : "delete", 
			number : num, 
		},
		function(data,status){
			if(status == "success"){
				alert(num + _(" Deleted from the blacklist."));
				$("#row"+num).fadeOut(2000,function(){
					$(this).remove();
				});
			}
		}
	);
});

$('#Upload').on('click',function(){
	var file = document.getElementById("blacklistfile");
	var formData = new FormData();
	formData.append("blacklistfile", file.files[0])
	var xhr = new XMLHttpRequest();
	xhr.open('POST','config.php?display=blacklist&action=import', true);
	xhr.send(formData);
	xhr.onreadystatechange = function(){
		if(xhr.status == 200){
			location.reload()
		}else{
			alert("Import Failed");
		}
	}
});	
//Bulk Actions
$('#action-toggle-all').on("change",function(){
	var tval = $(this).prop('checked');
	$('input[id^="actonthis"]').each(function(){
		$(this).prop('checked', tval);
	});
});

$('input[id^="actonthis"],#action-toggle-all').change(function(){
	if($('input[id^="actonthis"]').is(":checked")){
		$("#trashchecked").removeClass("hidden");
	}else{
		$("#trashchecked").addClass("hidden");
	}

});
//This does the bulk delete... 
$("#trashchecked").on("click",function(){
	var reload = false;
	$('input[id^="actonthis"]').each(function(){
		if($(this).is(":checked")){
			var num = $(this).val();
			$.post("config.php?display=blacklist",
				{
					action : "delete", 
					number : num, 
				},
				function(data,status){
					if(status == "success"){
						reload = true;
						$("#row"+num).fadeOut(2000,function(){
							$(this).remove();
						});
					}
				}
			);
		}
		if(reload){
			location.reload();
		}
	});
	//Reset ui elements
	//hide the action element in botnav
	$("#delchecked").addClass("hidden");
	//no boxes should be checked but if they are uncheck em.
	$('input[id^="actonthis"]').each(function(){
		$(this).prop('checked', false);
	});
	//Uncheck the "check all" box
	$('#action-toggle-all').prop('checked', false);
});
