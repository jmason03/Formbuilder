<?
	/*
		Resources Available:
		"formulier" = Formulier - List
	*/

?>
<div class="<?= $callout['colsize-css'] ?>">
    <div>
        <article>
            <h2><?=$callout['title']?></h2>
            <?
            $formID = $callout['formulier'];
            $contactForm = new TitForm;
            $contactForm = $contactForm->getForm($formID);
            ?>
        </article>
    </div>
</div>
