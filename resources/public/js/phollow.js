class Phollow {
    constructor() {
        this.isWindowActive = false;
        this.errorCount = 0;
        this.newErrorCount = 0;
        this.meta = null;
        this.ws = null;
        this.templateErrorRow = null;
        this.templateAlert = null;
    }

    run(done) {
        let that = this;
        window.onblur = function() {
            that.isWindowActive = false;
        };
        window.onfocus = function () {
            that.isWindowActive = true;
            if (that.errorCount) {
                Phollow.setHat('black');
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
                .then(done);
        } catch (e) {
            this.pushAlert('danger', 'Initialization exception', e);
            console.log("Exception", e);
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
            $('#status').empty().append($('<span class="text-success">').text('Connected'));
        };
        this.ws.onclose = function () {
            that.pushAlert('danger', 'WebSocket Connection', "The connection has been closed by the server");
            that.ws = null;
            Phollow.setTitle(0);
            $('#status').empty().append($('<span class="text-danger">').text('Disconnected'));
            $('.header-bar').addClass('alert-danger');
        };
        this.ws.onmessage = function (message) {
            console.log(message);
            try {
                const document = JSON.parse(message.data);
                const type = document['meta']['type'];
                const data = document['data'];
                switch (type) {
                    case 'error':
                        that.pushError(data);
                        break;
                    default:
                        console.log("Unknown message type", type);
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

    pushError(error) {
        if (!this.isWindowActive) {
            if (!this.newErrorCount) {
                Phollow.setHat('red');
            }
            Phollow.setTitle(++this.newErrorCount);
        }
        ++this.errorCount;
        switch (error['severityName']) {
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
        const $error = $(
            this.templateErrorRow(error)
        );
        $('#tbody-errors').append($error);
    };

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
        this.templateErrorRow = Phollow.compileTemplate('template-error-row');
        this.templateAlert = Phollow.compileTemplate('template-alert');
    }
}

$(function () {
    let phollow = (new Phollow);
    phollow.run(function () {
    });
});
