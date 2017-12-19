/**
 * Novo SGA - Settings
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'

    var app = new Vue({
        el: '#settings',
        data: {
            locais: locais,
            impressao: impressao,
            usuarios: usuarios,
            contadores: {},
            servicos: [],
            servicosSearch: '',
            servicosUnidade: [],
            servicoUsuario: {},
            servicoUnidade: null
        },
        computed: {
            availableServices: function () {
                var map = {}, self = this;

                this.usuarios.forEach(function (user) {
                    map[user.id] = (self.servicosUnidade || []).filter(function (su) {
                        var userServices = user.servicos || [], available = true;

                        for (var i = 0; i < userServices.length; i++) {
                            if (userServices[i].id === su.servico.id) {
                                available = false;
                                break;
                            }
                        }

                        return available;
                    });
                });

                return map;
            }
        },
        methods: {
            showServicos: function () {
                this.loadServicos();
                $('#dialog-servicos').modal('show');
            },

            loadServicos: function () {
                var self = this,
                    ids  = self.servicosUnidade.map(function (su) {
                        return su.servico.id;
                    });

                self.servicos = [];

                App.ajax({
                    url: App.url('/novosga.settings/servicos'),
                    data: {
                        ids: ids.join(',')
                    },
                    success: function (response) {
                        self.servicos = response.data;
                    }
                });
            },

            addServicos: function () {
                var self = this,
                    ids = [];

                this.servicos.forEach(function (servico) {
                    if (servico.checked) {
                        ids.push(servico.id);
                    }
                });

                App.ajax({
                    url: App.url('/novosga.settings/servicos_unidade'),
                    type: 'post',
                    data: {
                        ids: ids
                    },
                    success: function () {
                        self.loadServicosUnidade();
                        $('#dialog-servicos').modal('hide');
                    }
                });
            },

            loadServicosUnidade: function () {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.settings/servicos_unidade'),
                    success: function (response) {
                        self.servicosUnidade = response.data.map(function (servico) {
                            servico.local = servico.local ? servico.local.id : null;
                            servico.departamento = servico.departamento ? servico.departamento.id : null;
                            return servico;
                        });
                    }
                });
            },

            loadContadores: function () {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.settings/contadores'),
                    success: function (response) {
                        self.contadores = {};
                        for (var i = 0; i < response.data.length; i++) {
                            var contador = response.data[i];
                            self.contadores[contador.servico.id] = contador.numero;
                        }
                    }
                });
            },

            updateServico: function (servicoUnidade) {
                var data = Object.assign({}, servicoUnidade);

                data.sigla = data.sigla.toUpperCase();
                delete data.servico;

                return App.ajax({
                    url: App.url('/novosga.settings/servicos_unidade/') + servicoUnidade.servico.id,
                    type: 'put',
                    data: data
                });
            },

            removeServicoUnidade: function (servicoUnidade) {
                var self = this;

                if (servicoUnidade.ativo) {
                    return;
                }

                App.ajax({
                    url: App.url('/novosga.settings/servicos_unidade/') + servicoUnidade.servico.id,
                    type: 'delete',
                    success: function () {
                        self.loadServicosUnidade();
                    }
                });
            },

            updateServicoFromModal: function () {
                var self = this;

                this.updateServico(this.servicoUnidade).then(function () {
                    self.loadServicosUnidade();
                    $('#dialog-servico-unidade').modal('hide');
                });
            },

            showModal: function (su) {
                this.servicoUnidade = Object.assign({}, su);

                $('#dialog-servico-unidade').modal('show');
            },

            uppercase: function (su) {
                su.sigla = (su.sigla || '').toUpperCase();
            },

            updateImpressao: function () {
                var data = Object.assign({}, this.impressao);

                return App.ajax({
                    url: App.url('/novosga.settings/update_impressao'),
                    type: 'post',
                    data: data
                });
            },

            reiniciarContator: function (servicoId) {
                var self = this;
                if (!confirm(desejaReiniciar)) {
                    return;
                }
                App.ajax({
                    url: App.url('/novosga.settings/reiniciar/') + servicoId,
                    complete: function () {
                        self.loadContadores();
                    }
                });
            },

            limparSenhas: function () {
                var self = this;
                if (!confirm(desejaLimparDados)) {
                    return;
                }
                App.ajax({
                    url: App.url('/novosga.settings/limpar'),
                    complete: function () {
                        self.loadContadores();
                    }
                });
            },

            reiniciarSenhas: function () {
                if (!confirm(desejaReiniciarSenhas)) {
                    return;
                }

                var self = this;
                App.ajax({
                    url: App.url('/novosga.settings/acumular_atendimentos'),
                    type: 'post',
                    complete: function () {
                        self.loadContadores();
                    }
                });
            },

            addServicoUsuario: function (usuario) {
                if (!this.servicoUsuario[usuario.id]) {
                    return;
                }

                var su = this.servicoUsuario[usuario.id];

                for (var i = 0; i < usuario.servicos.length; i++) {
                    if (su.servico.id === usuario.servicos[i].id) {
                        return;
                    }
                }

                App.ajax({
                    url: App.url('/novosga.settings/servico_usuario/') + usuario.id + '/' + su.servico.id,
                    type: 'post',
                    success: function (response) {
                        usuario.servicos.push({
                            id: response.data.servico.id,
                            sigla: su.sigla,
                            nome: response.data.servico.nome,
                            peso: response.data.peso
                        });
                        
                        App.Websocket.emit('change user', {
                            user: usuario.id,
                            unity: unidade.id
                        });
                    }
                });
            },

            removeServicoUsuario: function (usuario, servicoUsuario) {
                App.ajax({
                    url: App.url('/novosga.settings/servico_usuario/') + usuario.id + '/' + servicoUsuario.id,
                    type: 'delete',
                    success: function () {
                        usuario.servicos.splice(usuario.servicos.indexOf(servicoUsuario), 1);
                        
                        App.Websocket.emit('change user', {
                            user: usuario.id,
                            unity: unidade.id
                        });
                    }
                });
            },

            updateServicoUsuario: function (usuario, servicoUsuario) {
                App.ajax({
                    url: App.url('/novosga.settings/servico_usuario/') + usuario.id + '/' + servicoUsuario.id,
                    type: 'put',
                    data: servicoUsuario,
                    success: function (response) {
                        servicoUsuario.peso = response.data.peso;
                    }
                });
            },

            init: function () {
                var self = this;
                
                App.Websocket.connect();

                App.Websocket.on('connect', function () {
                    App.Websocket.emit('register user', {
                        secret: wsSecret,
                        user: usuario.id,
                        unity: unidade.id
                    });
                });

                // ajax polling fallback
                App.Websocket.on('reconnect_failed', function () {
                    App.Websocket.connect();
                    console.log('ws timeout, ajax polling fallback');
                    self.loadContadores();
                });

                App.Websocket.on('error', function () {
                    console.log('error');
                });

                App.Websocket.on('register ok', function () {
                    console.log('registered!');
                });

                App.Websocket.on('update queue', function () {
                    console.log('do update!');
                    self.loadContadores();
                });
                
                this.loadServicosUnidade();
                this.loadContadores();
            }
        }
    });

    app.init();

})();
