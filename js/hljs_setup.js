//let hljs = require("../node_modules/highlightjs/highlight.pack");
import hljs from '../node_modules/highlight.js/lib/highlight';

['javascript', 'php', 'bash', 'sql', 'css', 'less', 'json'].forEach((langName) => {
    // Using require() here because import() support hasn't landed in Webpack yet
    const langModule = require(`../node_modules/highlight.js/lib/languages/${langName}`);
    hljs.registerLanguage(langName, langModule);
});

(function($, window) {
    var init_fn_flag = false;
    var init_fn = (function() {
        if (init_fn_flag)
            return;

        init_fn_flag = true;

        hljs.configure({"tabReplace":"    "});
        $('pre code').each(function(i, block) {
            hljs.highlightBlock(block);
        });

    });

    $(document).ready(init_fn);
    $(window).on("load", init_fn);
})(jQuery, window);
