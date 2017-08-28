window.onload = function() {

    $(".form-item:last-child").on('click', function(){
        $('.disabled-button').prop('disabled', false); //makes it enabled
    });

    // Maak verzend knop disabled tot form open is.
    $('.disabled-button').prop('disabled', true); //makes it disabled

    // Open 2e form blok

    $( ".form-item:last-child .form-input-group:last-child" ).change(function() {
        $('.disabled-button').prop('disabled', false); //makes it enabled

    });



    $( ".form-item .form-input-group:last-child" ).change(function() {
        $(this).parent().parent().find( ".form-disabled" ).first().addClass("form-enabled").removeClass("form-disabled");
    });

    $('.first-item').click(function(){
        var itemAmount = $(this).parent().find('.form-item').length;
        var itemz =  $(this).parent().find('.form-item');
        var cleared = $(this).parent().find('.cleared').length;

        if (itemAmount >= 2){
            $(itemz).each(function(index){
                if (index === 1 && cleared == 0){
                    $('<div class="cleared"></div>').insertAfter(this);

                }
            });
        }
    });




    // Extra input veld
    $( "select.field-item" ).change(function() {
        var selected = $(this).find("option");

        // var selectParent = $('select.field-item');
        // if (selected.hasClass('open-new-input')){
        //     $(selectParent).after('<input class="field-item extra-item" name="anders namelijk" type="text" placeholder="anders namelijk..." required>');
        // }else{
        //     $('.extra-item').remove();
        // }
    });
};