
// Script heeft jquery validation plugin nodig, helaas voor nu nog
function onSubmit() {
    $("#c-form").validate();
    if ($("#c-form").valid()){
        $("#c-form").submit();
    }
}


