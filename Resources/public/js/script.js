/**
 * Novo SGA - Settings
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function() {
    'use strict'

    var app = new Vue({
        el: '#settings',
        data: {
            locais: locais,
            impressao: impressao,
            contadores: {},
            servicos: [],
            servicoUnidade: {
                local: {}
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
                    self.servicoUnidade = {};
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
            init: function () {
                this.loadServicos();
                this.loadContadores();
            }
        }
    });

    app.init();

})();