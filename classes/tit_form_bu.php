<?
/**
 * Terra-IT Form Builder
 *
 * Bouw formulieren in BigTree CMS. That's it ¯\_(ツ)_/¯
 * @author Jordi vd Broek <jordi@terra-it.com>
 * @version 1.1.2
 */

//Changelog
// 1. Namen van formuliersecties zichtbaar
// 2. Formulier introductie tekst zichtbaar.
// 3. Fuck google captcha, het is eruit.
// 4. Random string honeypot en minimale form invultijd honeypot added
// 5. Anders namelijk... bug gefixt.
// 6. Cleaned up
// 7. Server side required velden check fixed
// 8. Elke keer dat een form word gegenereerd krijgt deze een unieke ID, dit maakt meerdere forms op een pagina mogelijk, ook minder kans op misbruik.
// 9. IP Adres van gebruiker word meegestuurd.

// Insert javascript in de head
require('required/insert_js.php');

class mailTemplater{

    public function getMailTemplate(){
        global $bigtree;
        $default = 'default.php';
        $defaultTemplate = file_get_contents('mail-template/' . $default, true);

        if ($template = 'default'){
            $template = $defaultTemplate;
        }

        $this->activeTemplate = $template;
        return $this;
    }

    public function formatTemplate($variables = false){

        $mailBody = $this->activeTemplate;

        if (isset($variables) && !empty($variables)){
            foreach($variables as $key => $value){
                $mailBody = str_replace($key, $value, $mailBody);
            }
        }

        return $mailBody;
    }
}

class TitForm extends BigTreeModule {
    var $Table = "tit_form";

    public function getForm($id){
        global $bigtree;

        // =======================================================================//
        // Security first. Maakt random bytes en zet deze verderop als input name en in sessie voor vergelijking.
        // Zet tijd verderop ook in input, check deze later tijdens post validatie.
        // =======================================================================//

        $noSpamIdentifier = md5(uniqid());
        if(!empty($_SESSION["spam-iden"])){
            // doe niks sessie is al gezet?
        }else{
            $_SESSION["spam-iden"] = $noSpamIdentifier;
        }

        // Zet tijdslimiet op 10 sec
        $time_limit = 10;

        // =======================================================================//

        //Haal basisvelden op
        $baseFields = new TitFormBaseFields;
        $baseFields = $baseFields->getApproved('position ASC');
        $formItems = $this->getApproved('position ASC');

        //Kies de juiste form
        foreach($formItems as $formItem){

            if ($formItem['id'] == $id){
                $formItems = $formItem;
                $form = true;
            }

            if ($formItem['type'] == 'calculate-form'){
                $formClass = 'calculate-item';
                $disabled = 'style="display:none;"';
                $buttonDisabled = 'disabled-button';
            }
        }


        if ($form = true){
            // Selecteer form 'stages' only
            foreach($formItem as $key => $value){
                if($key == 'form_stages'){
                    $stages[$key] = $value;
                }
            }

            // Verkrijg de juiste velden uit de mess
            foreach($stages as $stage){
                if(is_array($stage)){
                    foreach($stage as $stageItem){
                        if (isset($stageItem['section'])){
                            $fields[] = $stageItem['section'];
                        }
                    }
                }
            }

            // Veldjes loopen for required voor form opbouw
            if (isset($fields)){
                foreach($fields as $field){
                    foreach($field as $key => $value){
                        // Staan er basis velden in de lijst? haal ze op en gebruik ze.
                        foreach($baseFields as $baseField){
                            if($baseField['id'] == $value){
                                $fieldItems[] = $baseField;
                            }
                        }
                    }

                    // Titel voor een nieuw RTL programma: op zoek naar de error.
                    foreach($fieldItems as $fieldItem) {
                        if($fieldItem['required'] == 'on'){
                            if($fieldItem['type'] == 'email' && preg_match('/mail/',strtolower($fieldItem['title']))){
                                $errorInfos[] = array($fieldItem['type'] => $fieldItem['error']);
                            }else{
                                $errorInfos[] = array($fieldItem['title'] => $fieldItem['error']);
                            }
                        }
                    }

                    if ($errorInfos){
                        foreach($errorInfos as $errorInfo){
                            if (is_array($errorInfo)){
                                foreach($errorInfo as $key => $value){
                                    $rightError[$key] = $value;
                                }
                            }
                        }
                    }
                }
            }

            if($errorInfos){
                $errorInfoFields = array_unique($rightError, SORT_REGULAR);
            }
        }

        // Start als post waar is
        if ($_POST['form_send'] == 'true') {

            // Clean de post een beetje
            $post = array();
            foreach ($_POST as $key => $value) {

                if(is_array($value)){
                    foreach($value as $key => $value){
                        $post[$key] = Bigtree::safeEncode(strip_tags(trim($value)));
                    }
                }else{
                    $post[$key] = Bigtree::safeEncode(strip_tags(trim($value)));
                }
            }

            // Vergelijk sessie code met post, klopt dit niet, verzenden we niks.
            $antiSpamCode = $_SESSION["spam-iden"];
            if(isset($post['fietsbel']) && $post['fietsbel'] == $antiSpamCode && time()-$time_limit > (int)$post['form-time']){

                $postKeys = array_keys($post);

                // vergelijk titels, check voor required en paas errors
                foreach($errorInfoFields as $infoKey => $infoValue){
                    foreach($post as $key => $value){
                        if($key == str_replace(' ', '_', $infoKey) && empty($value)){
                            $errors[] = $infoValue;
                        }
                    }
                }

                foreach($fieldItems as $fieldItem){
                    foreach($fieldItem as $fieldKey => $fieldValue){
                        $fixedTitles[] = str_replace(' ', '_', $fieldValue);
                        $cleanTitles[] = $fieldValue;

                        foreach($fixedTitles as $fixedTitle){

                            foreach ($postKeys as $postKey) {
                                if ($postKey == $fixedTitle) {
                                    // Prepareer HTML email values;
                                    foreach ($post as $postKey => $postValue) {

                                        if ($postKey == 'email' && !empty($postValue)){
                                            $reply = true;
                                        }

                                        foreach($cleanTitles as $cleanTitle){
                                            $mailPrepares[$postKey] = $postValue;
                                        }

                                        foreach($mailPrepares as $key => $value){
                                            if($key == $fixedTitle){
                                                foreach($cleanTitles as $cleanTitle){
                                                    if (str_replace(' ', '_', $cleanTitle) == $key){
                                                        $mailItems[$cleanTitle] = $value;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }else{
                $errors[] = 'Spambot detected!';
            }


            if (is_array($errors)){
                $errors = array_unique($errors);
            }
            //Errors gevonden? laat maar zien, zo niet, verzend die mail
            if(count($errors) > 0){
                # display errors
                $errorsHTML = '<ul>';
                foreach ($errors as $error) {
                    $errorsHTML .= '<li>' . $error . '</li>';
                }
                $errorsHTML .= '</ul>';

            }else{
                $userIP = $_SERVER["REMOTE_ADDR"];
                $to = $formItems['email_to'];
                $subject = $formItems['subject'];
                $from = $formItems['email_from'];
                $return = $post['email'];
                $bcc = 'info@terra-it.com';

                // Zet email items
                foreach($mailItems as $key => $value){
                    $content .= '<tr>
                    <td style="padding: 5px; font-family: sans-serif; font-size: 15px; mso-height-rule: exactly; line-height: 20px; color: #555555;">
                        <strong>'. $key .'</strong>
                    </td>
                    <td style="padding: 5px; font-family: sans-serif; font-size: 15px; mso-height-rule: exactly; line-height: 20px; color: #555555;">'. $value .'</td>
                    </tr>';
                }
                $variables = array(
                    '{{subject}}' => $formItems['subject'],
                    '{{userIP}}' => $userIP,
                    '{{image}}' => BigTree::prefixFile($formItems['image'], "mail_"),
                    '{{message}}' => $content
                );
                $templater = new mailTemplater();
                $html = $templater->getMailTemplate('default')->formatTemplate($variables);
                $mainSend = BigTree::sendEmail($to, $subject, $html, "", $from, $return, false, $bcc, false);
                unset($_SESSION['spam-iden']);

                // Eerste mail verstuurd, nu de volgende als dit van toepassing is.
                $html = '';
                if ($reply = true && $mainSend = true){
                    $to = $post['email'];
                    $subject = $formItems['return_title'];
                    $from = $formItems['email_from'];
                    $html = html_entity_decode($formItems['reply_template'], ENT_QUOTES);
                    BigTree::sendEmail($to, $subject, $html, "", $from, false, false, false);
                }

                // Zet unieke form identifier in sessie voor later use (redirect etc)
                foreach($post as $postKey => $postValue){
                    if (!empty($postKey) && $postKey == 'identifier'){
                        $_SESSION["identifier"] = $postValue;
                    }
                }

                // Redirect naar de juiste identifier.
                $link = trim($page['link'], '/');
                BigTree::redirect($link.'?'.$_SESSION["identifier"]);
            }

            // Geen post data? maak unieke identifier aan.
        }elseif(empty($_POST)){
            $identifier = uniqid();
        }
        // Check voor identifiers in de url
        if (!empty($_SESSION['identifier'])){
            $urlIdentifier = $_SESSION['identifier'];
            foreach($_GET as $key => $value){
                if ($key == $urlIdentifier){
                    $success = true;
                }
            }
        }else{
            // doe niks
        }
        ?>

        <form action="" method="post" class="contact-form">
            <div class="grid">
                <div>
                    <input type="hidden" name="form_send" value="true"/>
                    <input style="display:none;" type="text" name="fietsbel" value="<?=$_SESSION['spam-iden']?>" placeholder="" />
                    <input style="display:none;" name="form-time" type="text" value="<?=time(); ?>" />
                    <input type="hidden" name="identifier" value="<?=$identifier?>">
                    <div class="col-12">
                        <?=(isset($errorsHTML) ? '<div id="attention" class="form-error">
                                '. $formItems[0]['error_introduction'] .'
                                '. $errorsHTML .'
                            </div>' : '')?>
                        <?if ($success == true) {
                            print('<div id="attention" class="form-success">Uw bericht is succesvol verzonden.</div>');
                        } ?>
                        <div class="form-intro">
                            <?
                            if(isset($formItems[0]['comment'])){
                                echo $formItems[0]['comment'];
                            }elseif(isset($formItems['comment'])){
                                echo $formItems['comment'];
                            }
                            ?>
                        </div>
                    </div>
                    <?
                    $amount = count($fields);
                    // Uit hoeveel onderdelen bestaat dit formulier? lets find out!
                    if ($amount == 1){
                        echo '<div class="col-12 col-small-12 col-mobile-12 form-item">';
                    }
                    if($amount > 1){
                        echo '<div class="col-6 col-small-12 col-mobile-12 form-item first-item">';
                    }
                    $i=0;
                    foreach($stages as $stage){

                        if(is_array($stage)){
                            foreach($stage as $stageItem){

                                // Show stage titel
                                if(isset($stageItem['title']) && !empty($stageItem['title'])){
                                    echo '<strong>'.$stageItem['title'].'</strong>';
                                }

                                // Tel op en loop
                                $i++;
                                if (isset($stageItem['section'])) {
                                    foreach ($stageItem['section'] as $section) {

                                        if (isset($fieldItems)) {
                                            $check = array();
                                            foreach ($fieldItems as $fieldItem) {
                                                if (isset($fieldItem['id']) && $fieldItem['id'] == $section) {
                                                    // Zorg voor alleen unieke velden
                                                    if (!in_array($fieldItem[0], $check)) {

                                                        $rtitle = str_replace(' ', '_', $fieldItem['title']);

                                                        ($fieldItem['required'] == 'on') ? $required = 'required' : $required = '';
                                                        // Textitems
                                                        switch ($fieldItem['type']) {
                                                            case 'text':
                                                                echo('<input class="field-item '.$fieldItem['type'].'" name="' . $fieldItem['title'] . '" value="' . $post[$rtitle] . '" type="text" placeholder="' . $fieldItem['title'] . '" ' . $required . '>');
                                                                break;
                                                            case 'email':
                                                                echo('<input class="field-item '.$fieldItem['type'].' " name="' . $fieldItem['type'] . '" value="' . $post[$rtitle] . '" type="email" placeholder="' . $fieldItem['title'] . '" ' . $required . '>');
                                                                break;
                                                            case 'tel':
                                                                echo('<input class="field-item '.$fieldItem['type'].'" name="' . $fieldItem['title'] . '" value="' . $post[$rtitle] . '" type="tel" pattern="^\d{10}$" placeholder="' . $fieldItem['title'] . '" ' . $required . '>');
                                                                break;
                                                            case 'number':
                                                                //Pak het woord dat tussen blokhaakjes staat bijvoorbeeld: "[meter]", zet deze in een variable en haal het uit de titel van het veld
                                                                $numberTitle = $fieldItem['title'];
                                                                preg_match_all("/\[[^\]]*\]/", $numberTitle, $matches);
                                                                $matches = implode(" ", $matches[0]);
                                                                $units = trim($matches,"[]");

                                                                $numberTitle = str_replace($matches, '', $numberTitle);
                                                                $numberTitle = trim($numberTitle);

                                                                echo '<div class="numberGroup">';
                                                                echo '<label for="'.$fieldItem['title'].'">'.$numberTitle.'</label>';
                                                                echo('<input id="'.$fieldItem['title'].'" class="field-item '.$fieldItem['type'].' " name="' . $numberTitle . '" value="' . $post[$rtitle] . '" type="number" min="0" max="999" placeholder="..." ' . $required . '><span>'.$units.'</span>');
                                                                echo '</div>';
                                                                break;
                                                            case 'textarea':
                                                                echo('<textarea class="field-item '.$fieldItem['type'].'" name="' . $fieldItem['title'] . '" placeholder="' . $fieldItem['title'] . '" ' . $required . '>' . $post[$rtitle] . '</textarea>');
                                                                break;
                                                            case 'radio':
                                                                echo (!empty($fieldItem['title'])) ? '<p>' . $fieldItem['title'] . '</p>' : '';
                                                                echo ('<radiogroup class="field-item">');
                                                                foreach ($fieldItem['sub_fields'] as $radioField) {
                                                                    $rtitle = str_replace(' ', '_', $radioField['title']);

                                                                    if ($radioField['option-type'] == 'textual'){
                                                                        $textualClass = true;
                                                                        $openInput = 'open-new-input';
                                                                        $textualTitle = $radioField['title'];
                                                                    }else{
                                                                        $textualClass = false;
                                                                        $openInput = '';
                                                                        $textualTitle = '';
                                                                    }
                                                                    // Input value als form errors aanwezig zijn
                                                                    if (isset($post) && in_array($rtitle, $post)) {
                                                                        echo('<div class="contain-radio"><input class="'.$fieldItem['type'].'  '. $openInput .'" type="radio" name="' . $fieldItem['title'] . '" id="' . $radioField['title'] . '" value="' . $radioField['title'] . '" ' . $required . ' checked/><label for="' . $radioField['title'] . '">' . $radioField['title'] . '</label></div>');
                                                                    } else {
                                                                        echo('<div class="contain-radio"><input class="'.$fieldItem['type'].'  '. $openInput .'" type="radio" name="' . $fieldItem['title'] . '" id="' . $radioField['title'] . '" value="' . $radioField['title'] . '" ' . $required . '/><label for="' . $radioField['title'] . '">' . $radioField['title'] . '</label></div>');
                                                                    }
                                                                }
                                                                echo ('</radiogroup>');
                                                                if($textualClass){
                                                                    echo('<input class="field-item otherwise" name="' . $textualTitle . '" value="' . $post[$rtitle] . '" type="text" placeholder="' . $textualTitle . '">');
                                                                }
                                                                break;
                                                            case 'checkbox':
                                                                echo (!empty($fieldItem['title'])) ? '<strong>' . $fieldItem['title'] . '</strong>' : '';

                                                                if (count($fieldItem['sub_fields']) > 1) {
                                                                    $chbxTitle = $fieldItem['title'];
                                                                } else {
                                                                    $chbxTitle = $fieldItem['title'];
                                                                }
                                                                echo ('<fieldset class="field-item">');
                                                                foreach ($fieldItem['sub_fields'] as $checkboxField) {
                                                                    $rtitle = str_replace(' ', '_', $checkboxField['title']);

                                                                    // Input value als form errors aanwezig zijn
                                                                    if (isset($post) && in_array($rtitle, $post)) {
                                                                        echo('<label>
                                                                            <input class="field-item '.$fieldItem['type'].'" type="checkbox" name="' . $chbxTitle . '" value="' . $checkboxField['title'] . '" ' . $required . ' checked>
                                                                                ' . $checkboxField['title'] . '
                                                                            </label>');
                                                                    } else {
                                                                        echo('<label>
                                                                            <input class="field-item '.$fieldItem['type'].'" type="checkbox" name="' . $chbxTitle . '" value="' . $checkboxField['title'] . '" ' . $required . '>
                                                                                ' . $checkboxField['title'] . '
                                                                            </label>');
                                                                    }
                                                                }
                                                                echo ('</fieldset>');
                                                                break;
                                                            case 'select':
                                                                echo (!empty($fieldItem['title'])) ? '<label for="'.$fieldItem['title'].'">' . $fieldItem['title'] . '</label>' : '';
                                                                echo('<select id="'.$fieldItem['title'].'" class="field-item '.$fieldItem['type'].'" name="' . $fieldItem['title'] . '" ' . $required . '>');
                                                                echo '<option value="">Select</option> ';
                                                                foreach ($fieldItem['sub_fields'] as $selectField) {
                                                                    $rtitle = str_replace(' ', '_', $selectField['title']);

                                                                    if ($selectField['option-type'] == 'textual'){
                                                                        $textualSelect = true;
                                                                        $openInput = 'open-new-input';
                                                                        $selectTitle = $selectField['title'];
                                                                    }else{
                                                                        $textualSelect = false;
                                                                        $openInput = '';
                                                                        $selectTitle = '';
                                                                    }

                                                                    // Input value als form errors aanwezig zijn
                                                                    if (isset($post) && in_array($rtitle, $post)) {
                                                                        echo('<option class="'. $openInput .'" value="' . $selectField['title'] . '" selected>' . $selectField['title'] . '</option> ');
                                                                    } else {
                                                                        echo('<option class="'. $openInput .'" value="' . $selectField['title'] . '">' . $selectField['title'] . '</option> ');
                                                                    }
                                                                }
                                                                echo('</select>');
                                                                if($textualSelect){
                                                                    echo('<input class="field-item otherwise" name="' . $selectTitle . '" value="' . $post[$rtitle] . '" type="text" placeholder="' . $selectTitle . '">');
                                                                }
                                                                break;
                                                        }
                                                        $check[] = $fieldItem[0];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                if($i < 4){
                                    echo '</div><div class="col-6 col-small-12 col-mobile-12 form-item '.$formClass.'" '.$disabled.'>';
                                }elseif($i <= 1){
                                    // do nothing
                                }
                            }
                        }
                    }
                    ?>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn <?=$buttonDisabled?>" name="form-submit">Verzenden</button>
                </div>
            </div>
        </form>
    <?}
}
?>