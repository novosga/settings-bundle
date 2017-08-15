/**
 * Novo SGA - Settings
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'
    
    Vue.directive('bs-switch', {
        inserted: function (el) {
            $(el).bootstrapSwitch().on('switchChange.bootstrapSwitch', function () {
                var e = document.createEvent('HTMLEvents');
                e.initEvent('change', true, true);
                el.dispatchEvent(e);
            });
        }
    });
    
    var app = new Vue({
        el: '#settings',
        data: {
            locais: locais,
            impressao: impressao,
            usuarios: usuarios,
            contadores: {},
            servicos: [],
            servicoUsuario: {},
            servicoUnidade: null
        },
        computed: {
            availableServices: function () {
                var map = {}, self = this;
                
                this.usuarios.forEach(function (user) {
                    map[user.id] = (self.servicos || []).filter(function (su) {
                        var userServices = user.servicos || [], available = true;
                        
                        for (var i = 0; i < userServices.length; i++) {
                            if (userServices[i].servico.id === su.servico.id) {
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
            loadServicos: function () {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.settings/servicos'),
                    success: function (response) {
                        self.servicos = response.data.map(function (servico) {
                            servico.local = servico.local.id;
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
                var data = $.extend({}, servicoUnidade);
                
                data.sigla = data.sigla.toUpperCase();
                delete data.servico;
                
                return App.ajax({
                    url: App.url('/novosga.settings/servicos/') + servicoUnidade.servico.id,
                    type: 'post',
                    data: data
                });
            },
            
            updateServicoFromModal: function () {
                var self = this;
                
                this.updateServico(this.servicoUnidade).then(function () {
                    self.loadServicos();
                });
            },
            
            showModal: function (su) {
                this.servicoUnidade = $.extend({}, su);
                
                $('#dialog-servico').modal('show');
            },
            
            uppercase: function (su) {
                su.sigla = (su.sigla || '').toUpperCase();
            },
            
            updateImpressao: function () {
                var data = $.extend({}, this.impressao);
                
                return App.ajax({
                    url: App.url('/novosga.settings/update_impressao'),
                    type: 'post',
                    data: data
                });
            },
            
            reiniciarContator: function (servicoId) {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.settings/reiniciar/') + servicoId,
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
                    if (su.servico.id === usuario.servicos[i].servico.id) {
                        return;
                    }
                }
                
                App.ajax({
                    url: App.url('/novosga.settings/servico_usuario/') + usuario.id + '/' + su.servico.id,
                    type: 'post',
                    success: function () {
                        usuario.servicos.push(su);
                    }
                });
            },
            
            removeServicoUsuario: function (usuario, servicoUnidade) {                
                App.ajax({
                    url: App.url('/novosga.settings/servico_usuario/') + usuario.id + '/' + servicoUnidade.servico.id,
                    type: 'delete',
                    success: function () {
                        for (var i = 0; i < usuario.servicos.length; i++) {
                            var su = usuario.servicos[i];
                            if (su.servico.id === servicoUnidade.servico.id) {
                                usuario.servicos.splice(usuario.servicos.indexOf(su), 1);
                                break;
                            }
                        }
                    }
                });
            },
            
            init: function () {
                this.loadServicos();
                this.loadContadores();
            }
        }
    });

    app.init();

})();