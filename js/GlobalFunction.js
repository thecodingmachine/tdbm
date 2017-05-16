let jQuery = require("../node_modules/jquery/src/jquery");

var GlobalFunction = function() {
    this.menuInitialize = function() {
        var menuEl = jQuery("nav > .navbar"); // get nav element

        var mousewheelevt = (/Firefox/i.test(navigator.userAgent)) ? "DOMMouseScroll" : "mousewheel" //FF doesn't recognize mousewheel as of FF3.x
        jQuery(document).bind(mousewheelevt, function(e){
            setTimeout(function() {
                var evt = window.event || e //equalize event object
                evt = evt.originalEvent ? evt.originalEvent : evt; //convert to originalEvent if possible
                var delta = evt.detail ? evt.detail * (-40) : evt.wheelDelta //check for detail first, because it is used by Opera and FF
                if (delta > 0) {
                    if (menuEl.length > 0) {
                        if (!menuEl.hasClass("collapsed")) {
                            menuEl.addClass("collapsed");
                            jQuery('.navbar-collapse').removeClass("in");
                        }
                    }
                }
                else {
                    if (menuEl.length > 0) {
                        if (menuEl.hasClass("collapsed")) {
                            menuEl.removeClass("collapsed");
                            jQuery('.navbar-collapse').removeClass("in");
                        }
                    }
                }
            }, 250);
        });
        jQuery('button.navbar-toggle').on('click', function() {
            if(!menuEl.hasClass("collapsed")) {
                menuEl.addClass("collapsed");
            }
        });
    }
}

jQuery(function() {
    var globalFunction = new GlobalFunction();
    globalFunction.menuInitialize();
});