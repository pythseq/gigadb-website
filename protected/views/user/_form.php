<div class="row">
	<div class="span8 offset2">
		<div class="clear"></div>
		<?php if ($model->isNewRecord) {?>
		<p><?=Yii::t('app' , 'GigaScience appreciates your interest in the GigaDB project. With a GigaDB account, you can submit new datasets to the database. Also, GigaDB can automatically notify you of new content which matches your interests. Please fill out the following information and register to enjoy the benefits of GigaDB membership!')?></p>
<?}
		?>
		<?php $text = $this->captchaGenerator(); ?>
		<p class="note"><?=Yii::t('app' , 'Fields with <span class="required">*</span> are required.')?></p>
		<div class="form well">
			<? $form=$this->beginWidget('CActiveForm', array(
				'id'=>'user-form',
				'enableAjaxValidation'=>false,
				'htmlOptions'=>array('class'=>'form-horizontal')
			)) ?>
				<div class="control-group">
					<?= $form->labelEx($model,'email', array('class'=>'control-label')) ?>
					<div class="controls">
						<?= $form->textField($model,'email',array('size'=>30,'maxlength'=>128)) ?>
						<?= $form->error($model,'email') ?>
					</div>
				</div>

				<div class="control-group">
					<?= $form->labelEx($model,'first_name', array('class'=>'control-label')) ?>
					<div class="controls">
						<?= $form->textField($model,'first_name',array('size'=>30,'maxlength'=>60)) ?>
						<?= $form->error($model,'first_name') ?>
					</div>
				</div>

				<div class="control-group">
					<?= $form->labelEx($model,'last_name', array('class'=>'control-label')) ?>
					<div class="controls">
						<?= $form->textField($model,'last_name',array('size'=>30,'maxlength'=>60)) ?>
						<?= $form->error($model,'last_name') ?>
					</div>
				</div>

				<div class="control-group">
					<?= $form->labelEx($model,'password', array('class'=>'control-label')) ?>
					<div class="controls">
						<?= $form->passwordField($model,'password',array('size'=>30,'maxlength'=>60)) ?>
						<?= $form->error($model,'password') ?>
					</div>
				</div>

				<div class="control-group">
					<?= $form->labelEx($model,'password_repeat', array('class'=>'control-label')) ?>
					<div class="controls">
						<?= $form->passwordField($model,'password_repeat',array('size'=>30,'maxlength'=>60)) ?>
						<?= $form->error($model,'password_repeat') ?>
					</div>
				</div>
				<? if (Yii::app()->user->checkAccess('admin')) { ?>
					<div class="control-group">
						<?= $form->labelEx($model,'role', array('class'=>'control-label')) ?>
						<div class="controls">
							<?= $form->dropDownList($model,'role',array('user'=>'user','admin'=> 'admin')) ?>
							<?= $form->error($model,'role') ?>
						</div>
					</div>
				<? } ?>
				<div class="control-group">
					<?= $form->labelEx($model,'affiliation', array('class'=>'control-label')) ?>
					<div class="controls">
						<?= $form->textField($model,'affiliation',array('size'=>30,'maxlength'=>60)) ?>
						<?= $form->error($model,'affiliation') ?>
					</div>
				</div>
				<div class="control-group">
					<?= $form->labelEx($model,'preferred_link', array('class'=>'control-label')) ?>
					<div class="controls">
						<?= CHtml::activeDropDownList($model,'preferred_link', User::$linkouts, array()) ?>
						<?= $form->error($model,'preferred_link') ?>
					</div>
				</div>
			    <div class="control-group">
				    <div class="controls">
				    	<?php echo $form->checkbox($model,'newsletter'); ?>
						<label><?=Yii::t('app' , 'Add me to GigaDB\'s mailing list')?></label>
				    </div>
			    </div>


			<? if ($model->isNewRecord) { ?>
			<div class="control-group">		
					<?php echo $form->labelEx($model,'verifyCode'); ?>		
			        <div class="controls">					
						<div style="width:100%">	
							<img style="width:200px;" src="/images/tempcaptcha/<?php echo $text; ?>.png">	
						</div>
						<?php echo $form->textField($model,'verifyCode'); ?>	
						<div class="hint">Please enter the letters as they are shown in the image above.
						<br/>Letters are not case-sensitive.</div>
						<?php echo $form->error($model, 'verifyCode'); ?>					
						</div>		
			    </div>
			<? } ?>


		</div><!--well-->
		<div class="pull-right">
			<?= MyHtml::submitButton($model->isNewRecord ? Yii::t('app' , 'Register') : 'Save', array('class'=>'btn-green')) ?>
		</div>

	<? $this->endWidget() ?>
	<?php 
		$path = "images/tempcaptcha/".$text.".png";
		$files = glob('images/tempcaptcha/*');
		foreach($files as $file){ 
		  if (is_file($file))
		  	if ($file != $path)
		  		 unlink($file); 
		}
	?>	
	</div><!--span8-->
</div><!-- user-form -->

