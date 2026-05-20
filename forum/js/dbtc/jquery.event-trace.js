// Create a private scope.
(function( $, on ){
 
 
    // I proxy the given function and return the resultant GUID
    // value that jQuery has attached to the function.
    var createAndReturnProxyID = function( target ){
 
        // When generating the proxy, it doesn't matter what the
        // "context" is (window in this case); we never actually
        // use the proxy - we just need the GUID that is tied to
        // it.
        var proxy = $.proxy( target, window );
 
        return( proxy.guid );
 
    };
 
 
    // Given an arguments collection (as the first argument), I return
    // the first value that is a function.
    var getFirstFunctionInstance = function( args ){
 
        for (var i = 0 ; i < args.length ; i++){
 
            // If this is a function, return it.
            if ($.isFunction( args[ i ])){
 
                return( args[ i ] );
 
            }
 
        }
 
    };
 
 
    // I print a horizontal rule to the console.
    var printHRule = function(){
 
        console.log( ". . . . . ." );
 
    };
 
 
    // I attempt to print the current stack trace.
    var printStackTrace = function(){
 
        // In order to gain access to the stack trace, let's throw
        // and catch an error object.
        try {
 
            throw new Error( "printStackTraceError" );
 
        } catch (error){
 
            // Get the raw trace (cross-browser).
            var rawTrace = (error.stack || error.stacktrace || "");
 
            // Break the raw trace into individual lines for printing.
            var stackTrace = rawTrace.split( /\n/g );
 
            // When printing the stack trace, we are going to start
            // on index 3. This allows us to skip the common parts:
            // 1 - *this* function.
            // 2 - the on() wrapper.
            // 3 - the core on() method.
            for (var i = 3 ; i < stackTrace.length ; i ++){
 
                if (stackTrace[ i ].length){
 
                    console.log( " > " + stackTrace[ i ] );
 
                }
 
            }
 
        }
 
    };
 
 
    // I use a timer to debounce hrule requests. This way, several
    // successive calls to the function only result in one HRule
    // printing.
    var showHRuleAfterPause = function(){
 
        clearTimeout( hruleTimer );
 
        // Print the hrule if the timer is allowed to run its course.
        hruleTimer = setTimeout( printHRule, hruleTimerDuration );
 
    };
 
 
    // We are going to be overriding the core on() method in the
    // jQuery library. But, we do want to have access to the core
    // version of the on() method for binding. Let's get a reference
    // to it for later use.
    var coreOnMethod = $.fn.on;
 
    // To help keep track of the bind-trigger relationship, we are
    // going to assign a unique ID to each bind instance.
    var bindCount = 0;
 
    // I enable the debouncing of the hrule print.
    var hruleTimer = null;
    var hruleTimerDuration = (.5 * 1000);
 
 
    // Override the core on() method so that we can inject logging
    // around the binding / trigger of the event handlers.
    $.fn.on = function( types, selector, data, fn, /*INTERNAL*/ one ){
 
        // Get the unique bind index for this event handler. We can
        // use this index value to connect the bind to the subsequent
        // trigger events.
        var bindIndex = ++bindCount;
 
        // Print the general bind() properties.
        console.log(
            ("Bind[ " + bindIndex + " ][ " + types + " ]:"),
            (selector || "*no-selector*"),
            this
        );
 
        // Print the stack trace at the time of the binding (so that
        // we can more easily track down where bindings are coming
        // from).
        printStackTrace();
 
        // the on() method accepts different argument schemes; as
        // such, the fn arguemnt may NOT be the actual function. Let's
        // just grab the first Function instance.
        var fnArgument = getFirstFunctionInstance( arguments );
 
        // Wrap the incoming event handler so that we can log
        // information surrounding its use.
        var fnWrapper = function( event ){
 
            // Log the event handler usage and event.
            console.log(
                ("Trigger[ " + bindIndex + " ]:"),
                event.type,
                event
            );
 
            // Print the HRULE (so the console is easer to read).
            showHRuleAfterPause();
 
            // Execute the underlying event handler.
            return(
                fnArgument.apply( this, arguments )
            );
 
        };
 
        // Tie the wrapper and the underlying event handler together
        // using jQuery's proxy() functionality - this way, the events
        // can be property unbind() with the wrapper in place.
        // fnWrapper.guid = createAndReturnProxyID( fnArgument );
 
        // Bind the wrapper as the event handler using the core,
        // underlying on() method.
        return(
            coreOnMethod.call( this, types, selector, data, fnWrapper, one )
        );
 
    };
 
 
})( jQuery );