/*
 * Behaviour of the showcase screens: tabs of an example, width and background choices, height of the frames, the form
 * that tries other values.
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

    // ---------------------------------------------------------------------------------------------------------------
    // The Try form of an example: the values of the props are sent as JSON 400 ms after the last change and the answer
    // is put in the frame of the example. Field API for modules: see "Fields of a host" in the README.
    // ---------------------------------------------------------------------------------------------------------------

    var DEBOUNCE_MS = 400;
    var RENEW_BEFORE_MS = 120000;

    function message(form, name, replacements) {
        var text = form.getAttribute('data-message-' + name) || '';
        Object.keys(replacements || {}).forEach(function (key) {
            text = text.replace('%' + key + '%', replacements[key]);
        });

        return text;
    }

    // How each built-in type of field reads and writes its control: `read` returns the JSON-safe value or throws an
    // Error whose message is shown at the field, `write` puts a value in the control.
    var builtinHandlers = {
        text: function (field) {
            var control = field.control;

            return {
                read: function () {
                    var text = control.value;
                    if (text === '' && field.nullable) {
                        return null;
                    }
                    if (field.numeric && /^-?\d+(\.\d+)?$/.test(text.trim())) {
                        return Number(text);
                    }

                    return text;
                },
                write: function (value) {
                    control.value = value === null || value === undefined ? '' : String(value);
                }
            };
        },
        number: function (field) {
            var control = field.control;

            return {
                read: function () {
                    var text = control.value.trim();

                    return text === '' ? null : Number(text);
                },
                write: function (value) {
                    control.value = value === null || value === undefined ? '' : String(value);
                }
            };
        },
        checkbox: function (field) {
            var control = field.control;

            return {
                read: function () {
                    return control.checked;
                },
                write: function (value) {
                    control.checked = !!value;
                }
            };
        },
        select: function (field) {
            var control = field.control;

            return {
                read: function () {
                    var option = control.options[control.selectedIndex];

                    return option ? JSON.parse(option.getAttribute('data-json')) : null;
                },
                write: function (value) {
                    var wanted = JSON.stringify(value === undefined ? null : value);
                    Array.prototype.forEach.call(control.options, function (option, index) {
                        if (option.getAttribute('data-json') === wanted) {
                            control.selectedIndex = index;
                        }
                    });
                }
            };
        },
        json: function (field, form) {
            var control = field.control;

            return {
                read: function () {
                    var text = control.value.trim();
                    if (text === '') {
                        if (field.nullable) {
                            return null;
                        }
                        throw new Error(message(form, 'json-empty'));
                    }
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        throw new Error(message(form, 'json', { error: error.message }));
                    }
                },
                write: function (value) {
                    control.value = value === undefined ? '' : JSON.stringify(value, null, 2);
                }
            };
        }
    };

    var modules = {};

    // A module is imported once, however many fields use it.
    function loadModule(url) {
        if (!modules[url]) {
            modules[url] = import(url);
        }

        return modules[url];
    }

    function initTry(form) {
        var frame = document.querySelector('iframe[name="' + form.getAttribute('data-frame') + '"]');
        if (!frame) {
            return;
        }

        var originalSource = frame.getAttribute('src');
        var link = {
            url: form.getAttribute('action'),
            token: form.getAttribute('data-token'),
            expires: parseInt(form.getAttribute('data-expires') || '0', 10) * 1000
        };
        var tokenField = form.querySelector('input[name="_token"]');
        var status = form.querySelector('[data-try-status]');
        var alertBox = form.querySelector('[data-try-alert]');
        var showButton = form.querySelector('[data-try-show]');
        var timer = null;
        var sequence = 0;
        var controller = null;
        var renewing = null;
        var byName = {};

        // JavaScript sends by itself: the button that does it without is not needed.
        if (showButton) {
            showButton.hidden = true;
        }

        var fields = all(form, '[data-try-field]').map(function (element) {
            var field = {
                element: element,
                name: element.getAttribute('data-prop'),
                type: element.getAttribute('data-field-type'),
                control: element.querySelector('[data-try-control]'),
                nullable: element.hasAttribute('data-nullable'),
                numeric: element.hasAttribute('data-numeric'),
                sent: element.hasAttribute('data-sent'),
                dirty: false,
                handler: null,
                initial: element.hasAttribute('data-initial') ? JSON.parse(element.getAttribute('data-initial')) : undefined,
                errorElement: element.querySelector('[data-try-error]')
            };

            // What a module gets: the same object, through these few methods.
            field.handle = function (handler) {
                field.handler = handler;
            };
            field.getValue = function () {
                return field.handler.read();
            };
            field.setValue = function (value) {
                field.handler.write(value);
            };
            field.notify = function () {
                field.dirty = true;
                schedule();
            };
            field.setError = function (text) {
                if (field.errorElement) {
                    field.errorElement.textContent = text || '';
                    field.errorElement.hidden = !text;
                }
                all(element, '[data-try-control]').forEach(function (control) {
                    if (text) {
                        control.setAttribute('aria-invalid', 'true');
                    } else {
                        control.removeAttribute('aria-invalid');
                    }
                });
            };

            byName[field.name] = field;

            return field;
        });

        fields.forEach(function (field) {
            var builtin = builtinHandlers[field.type];
            var moduleUrl = field.element.getAttribute('data-module');

            if (moduleUrl) {
                // The module says how the value is read; until it does, the field is not sent.
                loadModule(moduleUrl).then(function (module) {
                    var init = typeof module.default === 'function' ? module.default : module.init;
                    if (typeof init !== 'function') {
                        throw new Error('No default export');
                    }
                    init(field);
                    if (!field.handler && field.control) {
                        field.handler = builtinHandlers.text(field, form);
                    }
                }).catch(function () {
                    field.setError(message(form, 'module'));
                });
            } else if (builtin && field.control) {
                field.handler = builtin(field, form);
            } else if (field.control) {
                field.handler = builtinHandlers.text(field, form);
            }
        });

        function say(text) {
            if (status) {
                status.textContent = text;
            }
        }

        function showAlert(texts) {
            if (!alertBox) {
                return;
            }
            alertBox.textContent = texts.join('\n');
            alertBox.hidden = texts.length === 0;
        }

        function clearErrors() {
            fields.forEach(function (field) {
                field.setError(null);
            });
            showAlert([]);
        }

        // The values of the fields the example sets or the visitor changed; null (and the errors shown) when one cannot be read.
        function collect() {
            var body = {};
            var valid = true;
            fields.forEach(function (field) {
                if (!field.handler || !(field.sent || field.dirty)) {
                    return;
                }
                try {
                    body[field.name] = field.handler.read();
                } catch (error) {
                    valid = false;
                    field.setError(error.message);
                }
            });

            return valid ? body : null;
        }

        // Errors of the server by path (`vehicle.price`, `others[1].name`): next to the field of the first segment.
        function showServerErrors(errors) {
            var general = [];
            var perField = {};
            errors.forEach(function (error) {
                var path = typeof error.path === 'string' ? error.path : '';
                var match = /^[^.[]+/.exec(path);
                var field = match ? byName[match[0]] : null;
                if (!field) {
                    general.push(path === '' ? error.message : path + ': ' + error.message);

                    return;
                }
                (perField[field.name] = perField[field.name] || []).push(path === field.name ? error.message : path + ': ' + error.message);
            });
            Object.keys(perField).forEach(function (name) {
                byName[name].setError(perField[name].join('\n'));
            });
            showAlert(general);
        }

        function renew() {
            if (!renewing) {
                renewing = window.fetch(form.getAttribute('data-link-url'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        link.url = data.url;
                        link.token = data.token;
                        link.expires = data.expires ? data.expires * 1000 : 0;
                        form.setAttribute('action', data.url);
                        if (tokenField) {
                            tokenField.setAttribute('value', data.token);
                            tokenField.value = data.token;
                        }
                    })
                    .then(function () {
                        renewing = null;
                    }, function (error) {
                        renewing = null;
                        throw error;
                    });
            }

            return renewing;
        }

        function post(body, mine, retry) {
            controller = typeof AbortController === 'function' ? new AbortController() : null;

            return window.fetch(link.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': link.token },
                body: JSON.stringify(body),
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            }).then(function (response) {
                if (mine !== sequence) {
                    return null;
                }
                if (response.status === 200) {
                    return response.text().then(function (html) {
                        if (mine === sequence) {
                            frame.srcdoc = html;
                            say(message(form, 'updated'));
                        }
                    });
                }
                if (response.status === 422) {
                    return response.json().then(function (data) {
                        if (mine === sequence) {
                            showServerErrors(Array.isArray(data.errors) ? data.errors : []);
                        }
                    });
                }
                if ((response.status === 404 || response.status === 403) && retry) {
                    return renew().then(function () {
                        return mine === sequence ? post(body, mine, false) : null;
                    });
                }
                showAlert([message(form, 'failed', { status: String(response.status) })]);

                return null;
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                if (mine === sequence) {
                    showAlert([message(form, 'network')]);
                }
            });
        }

        function send() {
            window.clearTimeout(timer);
            timer = null;
            if (controller) {
                controller.abort();
            }
            sequence += 1;
            var mine = sequence;

            clearErrors();
            var body = collect();
            if (body === null) {
                // The preview stays as it was: never an empty frame.
                return;
            }

            var ready = link.expires && link.expires - Date.now() < RENEW_BEFORE_MS ? renew().catch(function () {
                // The old address may still work: try with it.
            }) : Promise.resolve();
            ready.then(function () {
                if (mine === sequence) {
                    return post(body, mine, true);
                }

                return null;
            });
        }

        function schedule() {
            window.clearTimeout(timer);
            timer = window.setTimeout(send, DEBOUNCE_MS);
        }

        form.addEventListener('input', function (event) {
            var element = event.target.closest ? event.target.closest('[data-try-field]') : null;
            var field = element ? byName[element.getAttribute('data-prop')] : null;
            // A field with a module tells by itself (`field.notify()`).
            if (field && !field.element.hasAttribute('data-module')) {
                field.dirty = true;
                schedule();
            }
        });
        form.addEventListener('change', function (event) {
            var element = event.target.closest ? event.target.closest('[data-try-field]') : null;
            var field = element ? byName[element.getAttribute('data-prop')] : null;
            // A field with a module tells by itself (`field.notify()`).
            if (field && !field.element.hasAttribute('data-module')) {
                field.dirty = true;
                schedule();
            }
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            send();
        });
        form.addEventListener('reset', function () {
            window.clearTimeout(timer);
            if (controller) {
                controller.abort();
            }
            sequence += 1;
            // The reset of the form is done after this event: the fields of modules are put back after it.
            window.setTimeout(function () {
                fields.forEach(function (field) {
                    field.dirty = false;
                    if (field.element.hasAttribute('data-module') && field.handler && field.initial !== undefined) {
                        field.handler.write(field.initial);
                    }
                });
                clearErrors();
                frame.removeAttribute('srcdoc');
                if (frame.getAttribute('src') !== originalSource) {
                    frame.setAttribute('src', originalSource);
                }
                say('');
            }, 0);
        });
    }

    // Side menu: always shown on a wide screen, folded behind the button of the header on a narrow one.
    function initMenu(menu) {
        var button = document.querySelector('[data-ux-showcase-menu-toggle]');
        if (!button) {
            return;
        }
        button.addEventListener('click', function () {
            var open = !menu.hasAttribute('data-open');
            if (open) {
                menu.setAttribute('data-open', '');
            } else {
                menu.removeAttribute('data-open');
            }
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function init() {
        document.body.classList.add('showcase-js');
        all(document, '[data-ux-showcase-menu]').forEach(initMenu);
        all(document, '[data-ux-showcase-tabs]').forEach(initTabs);
        all(document, '[data-ux-showcase-controls]').forEach(initControls);
        all(document, '[data-ux-showcase-try]').forEach(initTry);
        initFrameMessages();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
