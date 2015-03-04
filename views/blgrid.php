<?php
//Blacklist Rows
$blrows = '';
foreach($blacklist as $item){
		$number = $item['number'];
		$description = $item['description'];
		if($number == 'dest' || $number == 'blocked'){
			continue;
		}
$selLabel = _("Select");
$blrows .= <<<HERE
<tr id = "row$number">
<td>
<span class="radioset">
	<input type = "checkbox" class="" id="actonthis$number" name="actionList[]" value="$number">
	<label for="actonthis$number">$selLabel</label>
</span>
</td>
<td>$number</td>
<td>$description</td>
<td><a href="#" data-toggle="modal" data-target="#addNumber" data-number='$number' data-description='$description'>
	<i class="fa fa-edit"></i></a>
	<a href="#" id="del$number"  class='delAction' data-number="$number" >
	<i class="fa fa-trash-o"></i></a></td>
</tr>
HERE;
}
?>
<div class="table-responsive">
	<table class="table table-striped table-bordered">
		<thead>
			<tr>
				<th>
				<span class="radioset">
					<input type="checkbox" class="" id="action-toggle-all">
					<label for="action-toggle-all"><?php echo _("Select All")?></label>
				</span>
				</th>
				<th><?php echo _("Number") ?></th>
				<th><?php echo _("Description") ?></th>
				<th><?php echo _("Action") ?></th>
			</tr>
		</thead>
		<tbody>
			<?php echo $blrows ?>
		</tbody>
	</table>
</div>
