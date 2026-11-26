/*
 * Behaviour of the showcase screens: tabs of an example, width and background choices, height of the frames.
 * Standalone: no framework, no controller to register in the application, no inline handler.
 * Without it the pages still work: every panel is shown and the choices are links that reload the page.
 */
(function () {
    'use strict';

    var MESSAGE_TYPE = 'ux-showcase:frame';

    function all(root, selector) {
        return Array.prototype.slice.call(root.querySelectorAll(selector));
    }

    // Tabs: roles are in the markup, this adds the selection and the keyboard (arrows, Home, End).
    function initTabs(root) {
        var tabs = all(root, '[role="tab"]');
        var panels = tabs.map(function (tab) {
            return document.getElementById(tab.getAttribute('aria-controls'));
        });

        function select(index, focus) {
            tabs.forEach(function (tab, i) {
                var selected = i === index;
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
                panels[i].hidden = !selected;
            });
            if (focus) {
                tabs[index].focus();
            }
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                select(index, false);
            });
            tab.addEventListener('keydown', function (event) {
                var target = null;
                if (event.key === 'ArrowRight') {
                    target = (index + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    target = (index - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    target = 0;
                } else if (event.key === 'End') {
                    target = tabs.length - 1;
                }
                if (target !== null) {
                    event.preventDefault();
                    select(target, true);
                }
            });
        });

        select(0, false);
    }

    function frames() {
        return all(document, '.showcase-frame iframe');
    }

    function markCurrent(links, current) {
        links.forEach(function (link) {
            if (link === current) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    // Width and background: applied in place, and the address is updated so that it can be shared.
    function initControls(root) {
        var viewports = all(root, '[data-viewport]');
        var backgrounds = all(root, '[data-background]');

        viewports.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                var width = link.getAttribute('data-width');
                frames().forEach(function (frame) {
                    frame.style.width = width ? width + 'px' : '100%';
                });
                markCurrent(viewports, link);
                window.history.replaceState(null, '', link.href);
                // The links of the other group keep the choice of this one.
                backgrounds.forEach(function (other) {
                    var url = new URL(other.href);
                    url.searchParams.set('viewport', link.getAttribute('data-viewport'));
                    other.href = url.toString();
                });
            });
        });

        backgrounds.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                var background = link.getAttribute('data-background');
                frames().forEach(function (frame) {
                    var url = new URL(frame.getAttribute('src'), window.location.href);
                    if (background) {
                        url.searchParams.set('background', background);
                    } else {
                        url.searchParams.delete('background');
                    }
                    frame.setAttribute('src', url.pathname + url.search);
                });
                markCurrent(backgrounds, link);
                window.history.replaceState(null, '', link.href);
                viewports.forEach(function (other) {
                    var url = new URL(other.href);
                    if (background) {
                        url.searchParams.set('background', background);
                    } else {
                        url.searchParams.delete('background');
                    }
                    other.href = url.toString();
                });
            });
        });
    }

    // The bare page reports its height; a frame with a fixed height (data-fixed-height) ignores it.
    function initFrameMessages() {
        window.addEventListener('message', function (event) {
            var data = event.data;
            if (event.origin !== window.location.origin || !data || data.type !== MESSAGE_TYPE) {
                return;
            }
            frames().forEach(function (frame) {
                if (frame.contentWindow !== event.source) {
                    return;
                }
                if (!frame.hasAttribute('data-fixed-height') && typeof data.height === 'number') {
                    frame.style.height = data.height + 'px';
                }
                if (typeof data.background === 'string' && data.background !== '') {
                    frame.parentElement.style.backgroundColor = data.background;
                }
            });
        });
    }

    function init() {
        all(document, '[data-ux-showcase-tabs]').forEach(initTabs);
        all(document, '[data-ux-showcase-controls]').forEach(initControls);
        initFrameMessages();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
