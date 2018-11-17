class Phollow {
    constructor() {
        this.isWindowActive = false;
        this.errorCount = 0;
        this.newErrorCount = 0;
        this.meta = null;
        this.ws = null;
        this.templateScriptTable = null;
        this.templateScriptDataElement = null;
        this.templateAlert = null;
        this.$scriptContainer = null;
        this.scriptElements = {};
    }

    run(done) {
        let that = this;
        window.onblur = function () {
            that.isWindowActive = false;
        };
        window.onfocus = function () {
            that.isWindowActive = true;
            if (that.errorCount) {
                Phollow.setHat('green');
            }
            that.newErrorCount = 0;
            Phollow.setTitle(that.newErrorCount);
        };
        try {
            let that = this;
            $.when($.ajax('data/meta'))
                .then(
                    function (data) {
                        that.setMeta(data.data);
                    },
                    function () {
                        throw new Error("Failed fetching meta");
                    }
                )
                .then(
                    function () {
                        that.prepareWs();
                    }
                )
                .then(
                    function () {
                        that.compileTemplates();
                    }
                )
                .then(
                    function () {
                        that.$scriptContainer = $('#script-container');
                        $(document).on(
                            'click',
                            '.script-table thead',
                            function () {
                                let $tbody = $(this).siblings('tbody');
                                if ($tbody.is(':visible')) {
                                    $tbody.hide();
                                } else {
                                    $tbody.show();
                                }
                            }
                        );
                        $(function () {
                            $('body').tooltip(
                                {
                                    selector: '[data-toggle="tooltip"]'
                                }
                            );
                        })
                    }
                )
                .then(done);
        } catch (e) {
            this.pushAlert('danger', 'Initialization exception', e);
        }
    }

    static compileTemplate(id) {
        return Handlebars.compile(document.getElementById(id).innerHTML);
    }

    static setHat(icon) {
        let uri = 'images/hat-' + icon + '.png';
        $('#favicon').attr('href', uri);
    }

    static setTitle(count) {
        $('#title').text((count ? '[' + count + '] ' : '') + 'webgraphe/phollow');
    }

    prepareWs() {
        let server = this.meta['application']['server']['websocket'];
        let uri = 'ws://' + server['host'] + ':' + server['port'];
        this.ws = new WebSocket(uri);
        let that = this;

        this.ws.onopen = function () {
            Phollow.setHat('green');
            Phollow.setTitle(0);
            $('#status').toggleClass('badge-warning badge-success').text('Connected');
        };
        this.ws.onclose = function () {
            that.pushAlert('danger', 'WebSocket Connection', "The connection has been closed by the server");
            that.ws = null;
            Phollow.setTitle(0);
            $('#status').toggleClass('badge-success badge-danger').text('Disconnected');
            $('.header-bar').addClass('alert-danger');
        };
        this.ws.onmessage = function (message) {
            try {
                const document = JSON.parse(message.data);
                const type = document.meta.type;
                switch (type) {
                    case 'connectionOpened':
                        that.openConnection(document);
                        break;
                    case 'scriptStarted':
                        that.startScript(document);
                        break;
                    case 'error':
                        that.addError(document);
                        break;
                    case 'scriptEnded':
                        that.endScript(document);
                        break;
                    case 'connectionClosed':
                        that.closeConnection(document);
                        break;
                    default:
                        console.log("Unknown message type", type);

                        return false;
                }

                if (!that.isWindowActive) {
                    Phollow.setHat('red');
                }
            } catch (exception) {
                console.log("Exception", exception, message.data);
            }
        };
        this.ws.onerror = function (e) {
            that.pushAlert('danger', 'WebSocket Error', "An error occurred with the WebSocket");
            console.log("Error", e);
        };
    }

    openConnection(connectionOpened) {
        const scriptId = connectionOpened['meta']['scriptId'];
        const $scriptTable = $(this.templateScriptTable(connectionOpened));
        this.scriptElements[scriptId] = {
            errorCount: 0,
            tbody: $scriptTable.find('tbody'),
            thead_tr: $scriptTable.find('thead tr'),
            progress: $scriptTable.find('thead tr th i.script-progress'),
            errorCountBadge: $scriptTable.find('thead tr th span.script-error-count'),
            api: $scriptTable.find('.script-api'),
            hostname: $scriptTable.find('.script-hostname'),
            method: $scriptTable.find('.script-method'),
            path: $scriptTable.find('.script-path'),
            time: $scriptTable.find('.script-time'),
            feedback: $scriptTable.find('.script-feedback'),
        };
        this.$scriptContainer.append($scriptTable);
    }

    startScript(scriptStarted) {
        const scriptId = scriptStarted['meta']['scriptId'];
        let scriptElement = this.scriptElements[scriptId];
        scriptElement.thead_tr.toggleClass('bg-warning bg-primary');
        scriptElement.progress.toggleClass('fa-stopwatch fa-spin fa-spinner');
        scriptElement.api.text(scriptStarted['data']['serverApi']);
        scriptElement.hostname.text(scriptStarted['data']['hostname']);
        scriptElement.method.text(scriptStarted['data']['method']);
        scriptElement.path.text(scriptStarted['data']['path']);
    }

    addError(error) {
        ++this.errorCount;
        switch (error['data']['severityName']) {
            case 'ERROR':
            case 'PARSE':
            case 'CORE_ERROR':
            case 'COMPILE_ERROR':
            case 'USER_ERROR':
            case 'RECOVERABLE_ERROR':
                error['state'] = 'danger';
                break;
            case 'WARNING':
            case 'CORE_WARNING':
            case 'COMPILE_WARNING':
            case 'USER_WARNING':
                error['state'] = 'warning';
                break;
            case 'NOTICE':
            case 'USER_NOTICE':
                error['state'] = 'info';
                break;
            case 'DEPRECATED':
            case 'USER_DEPRECATED':
                error['state'] = 'secondary';
                break;
            case 'STRICT':
                error['state'] = 'primary';
                break;
            default:
                error['state'] = 'light';
                break;
        }
        const $error = $(this.templateScriptDataElement(error));
        const scriptId = error['meta']['scriptId'];
        let scriptElement = this.scriptElements[scriptId];
        scriptElement.tbody.append($error);
        if (!scriptElement.errorCount) {
            scriptElement.errorCountBadge.toggleClass('badge-secondary badge-warning');
        }
        scriptElement.errorCountBadge.text(++scriptElement.errorCount);
    }

    endScript(scriptEnded) {
        const scriptId = scriptEnded['meta']['scriptId'];
        let scriptElement = this.scriptElements[scriptId];
        scriptElement.thead_tr.toggleClass('bg-primary bg-success');
        scriptElement.progress.toggleClass('fa-spin fa-spinner fa-check-circle');
        scriptElement.time.html('<samp>' + scriptEnded['data']['time'].toFixed(3) + '<samp> <i class="fas fa-fw fa-stopwatch"></i>');
        if (!scriptElement.errorCount) {
            scriptElement.errorCountBadge.toggleClass('badge-secondary badge-success');
        }
    }

    closeConnection(connectionClosed) {
        const scriptId = connectionClosed['meta']['scriptId'];
        let scriptElement = this.scriptElements[scriptId];
        if (scriptElement.thead_tr.hasClass('bg-primary')) {
            scriptElement.thead_tr.toggleClass('bg-primary bg-danger');
            scriptElement.time.html('<i class="fas fa-fw fa-question-circle"></i>');
            scriptElement.feedback.text('Connection closed unexpectedly');
            scriptElement.progress.toggleClass('fa-spin fa-spinner fa-exclamation-triangle');
        } else {
            scriptElement.thead_tr.toggleClass('bg-success bg-secondary');
        }
    }

    pushAlert(level, title, body) {
        const $alert = $(
            this.templateAlert(
                {
                    type: level,
                    title: title,
                    body: body,
                    icon: 'exclamation-triangle'
                }
            )
        );
        const hide = function () {
            $alert.remove()
        };
        $alert.find('.delete').on('click', hide);
        $('#alerts').append($alert);
    }

    setMeta(meta) {
        this.meta = meta;
    }

    compileTemplates() {
        this.templateScriptTable = Phollow.compileTemplate('template-script-table');
        this.templateScriptDataElement = Phollow.compileTemplate('template-script-data-element');
        this.templateAlert = Phollow.compileTemplate('template-alert');
    }
}

$(function () {
    let phollow = (new Phollow);
    phollow.run(function () {
    });
});
