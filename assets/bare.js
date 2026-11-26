/*
 * Injected into the bare page of a preview by the showcase. Tells the page around the frame how tall the document is and
 * what its background is. Nothing here belongs to the example.
 */
(function () {
    'use strict';

    if (window.parent === window) {
        return;
    }

    var lastHeight = -1;
    var lastBackground = '';

    function background() {
        var transparent = /^(transparent|rgba\(0, 0, 0, 0\))$/;
        var body = window.getComputedStyle(document.body).backgroundColor;
        var root = window.getComputedStyle(document.documentElement).backgroundColor;

        return !transparent.test(body) ? body : (!transparent.test(root) ? root : '');
    }

    // The bottom of the body plus its margin: a document that is stretched to the height of the frame (100vh) is not
    // measured by its own height, which would never shrink.
    function height() {
        var style = window.getComputedStyle(document.body);

        return Math.ceil(document.body.getBoundingClientRect().bottom + window.pageYOffset + (parseFloat(style.marginBottom) || 0));
    }

    // A document put in the frame with `srcdoc` (the form that tries values) has the origin "null" for its location, but
    // the origin of the page around it: ask that page.
    function targetOrigin() {
        try {
            return window.parent.location.origin;
        } catch (error) {
            return window.location.origin;
        }
    }

    function report() {
        var current = height();
        var color = background();
        if (current === lastHeight && color === lastBackground) {
            return;
        }
        lastHeight = current;
        lastBackground = color;
        window.parent.postMessage({ type: 'ux-showcase:frame', height: current, background: color }, targetOrigin());
    }

    report();
    window.addEventListener('load', report);
    window.addEventListener('resize', report);
    if ('ResizeObserver' in window) {
        new ResizeObserver(report).observe(document.body);
    }
}());
