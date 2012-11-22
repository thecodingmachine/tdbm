<?php /* @var $this TdbmInstallController */ ?>
<div class="error">
<?php
echo $this->errorMsg;
?>
</div>

<form action="skip">
	<input type="hidden" name="selfedit" value="<?php echo $this->selfedit ?>" />
	<button>Skip install and continue</button>
</form>