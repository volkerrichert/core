
<!-- indexer::stop -->
<div class="<?php echo $this->class; ?> block"<?php echo $this->cssID; ?><?php if ($this->style): ?> style="<?php echo $this->style; ?>"<?php endif; ?>>
<?php if ($this->headline): ?>

<<?php echo $this->hl; ?>><?php echo $this->headline; ?></<?php echo $this->hl; ?>>
<?php endif; ?>

<?php if($this->empty): ?>
<p class="message empty"><?php echo $this->message; ?></p>
<?php else: ?>
<div class="form">
<?php if ($this->confirm): ?>

<p class="confirm"><?php echo $this->confirm; ?></p>
<?php else: ?>

<form action="<?php echo $this->action; ?>" id="<?php echo $this->formId; ?>" enctype="multipart/form-data" method="post">
<div class="formbody">
<input type="hidden" name="FORM_SUBMIT" value="<?php echo $this->formId; ?>" />
<input type="hidden" name="REQUEST_TOKEN" value="<?php echo REQUEST_TOKEN; ?>" />
<div class="widget">
	<?php echo $this->fields['email']->generateLabel(); ?> <?php echo $this->fields['email']->generateWithError(); ?> 
</div>
<?php if (isset($this->fields['captcha'])): ?>
<div class="widget">
	<?php echo $this->fields['captcha']->generateWithError(); ?> <label for="ctrl_captcha"><?php echo $this->fields['captcha']->generateQuestion(); ?><span class="mandatory">*</span></label>
</div>
<?php endif; ?>
<div class="submit_container">
  <input type="submit" class="submit" value="<?php echo $this->submit; ?>" />
</div>
</div>
</form>

<?php if ($this->hasError): ?>

<script type="text/javascript">
<!--//--><![CDATA[//><!--
window.scrollTo(null, ($('<?php echo $this->formId; ?>').getElement('p.error').getPosition().y - 20));
//--><!]]>
</script>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

</div>

</div>
<!-- indexer::continue -->