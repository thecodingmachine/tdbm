var $ = jQuery;
$( document ).ready(function() {
    $('.scroll-contact').on('click',function () {
        var contact = $('.b-container');
        $('html,body').animate({scrollTop: contact.offset().top}, 1500);
    });

    var wi = $(window).width();

    if(wi <= 980){
        $('#header').removeClass('absolute');
    }

    $(window).scroll(function() {
        if($(window).scrollTop() > 0) {
            $('#header').addClass('fixed');
        }else{
            $('#header').removeClass('fixed');
        }
    });

    // browser window scroll (in pixels) after which the "back to top" link is shown
    var offset = 300,
        //browser window scroll (in pixels) after which the "back to top" link opacity is reduced
        offset_opacity = 1200,
        //duration of the top scrolling animation (in ms)
        scroll_top_duration = 700,
        //grab the "back to top" link
        $back_to_top = $('.cd-top');

    //hide or show the "back to top" link
    $(window).scroll(function(){
        ( $(this).scrollTop() > offset ) ? $back_to_top.addClass('cd-is-visible') : $back_to_top.removeClass('cd-is-visible cd-fade-out');
        if( $(this).scrollTop() > offset_opacity ) {
            $back_to_top.addClass('cd-fade-out');
        }
    });

    //smooth scroll to top
    $back_to_top.on('click', function(event){
        event.preventDefault();
        $('body,html').animate({
                scrollTop: 0 ,
            }, scroll_top_duration
        );
    });
});
