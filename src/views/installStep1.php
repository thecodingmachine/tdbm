<?php /* @var $this TdbmInstallController */ ?>
<h1>Setting up TDBM</h1>

<p>TDBM can detect your database connection, set up the TdbmService instance and generate DAOs and Beans for your database automatically.
You can also do this manually later if you prefer.</p>
<p>The TDBM install procedure will create a "tdbmService" instance. By default, this instance does not use any cache (it uses the noCache
implementation). This is almost certainly suboptimal, but very convenient for development. For production use, be sure to use a proper
caching mechanism for the "tdbmService" instance.</p>

<form action="configure">
	<input type="hidden" name="selfedit" value="<?php echo $this->selfedit ?>" />
	<button class="btn btn-danger">Configure TDBM</button>
</form>
<form action="skip">
	<input type="hidden" name="selfedit" value="<?php echo $this->selfedit ?>" />
	<button class="btn">Skip</button>
</form>