/**
 * Novo SGA - Settings
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'

    new Vue({
        el: '#settings',
        data: {
            unidade: (unidade || {}),
            impressao: impressao,
            usuarios: usuarios,
            usuario: null,
            contadores: {},
            servicos: [],
            servicosSearch: '',
            servicosUnidade: [],
            servicoUsuario: {},
            servicoUnidade: null,
            servicosModal: null,
            servicoUnidadeModal: null,
        },
        computed: {
            availableServices: function () {
                let map = {};

                this.usuarios.forEach((user) => {
                    map[user.id] = (this.servicosUnidade || []).filter(function (su) {
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
            showServicos() {
                this.loadServicos().then(() => {
                    this.servicosModal.show();
                });
            },

            loadServicos() {
                const ids = this.servicosUnidade.map((su) => su.servico.id);
                this.servicos = [];
                return App.ajax({
                    url: App.url('/novosga.settings/servicos'),
                    data: {
                        ids: ids.join(',')
                    },
                    success: (response) => {
                        this.servicos = response.data;
                    }
                });
            },

            addServicos() {
                let ids = [];
                this.servicos.forEach((servico) => {
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
                    success: () => {
                        this.loadServicosUnidade();
                        this.servicosModal.hide();
                    }
                });
            },

            loadServicosUnidade() {
                App.ajax({
                    url: App.url('/novosga.settings/servicos_unidade'),
                    success: (response) => {
                        this.servicosUnidade = response.data.map((servico) => {
                            servico.departamento = servico.departamento ? servico.departamento.id : null;
                            return servico;
                        });
                    }
                });
            },

            loadContadores() {
                App.ajax({
                    url: App.url('/novosga.settings/contadores'),
                    success: (response) => {
                        this.contadores = {};
                        for (let i = 0; i < response.data.length; i++) {
                            const contador = response.data[i];
                            this.contadores[contador.servico.id] = contador.numero;
                        }
                    }
                });
            },

            updateServico(servicoUnidade) {
                const data = Object.assign({}, servicoUnidade);
                data.sigla = data.sigla.toUpperCase();
                delete data.servico;

                return App.ajax({
                    url: App.url('/novosga.settings/servicos_unidade/') + servicoUnidade.servico.id,
                    type: 'put',
                    data: data
                });
            },

            removeServicoUnidade(servicoUnidade) {
                if (servicoUnidade.ativo) {
                    return;
                }

                App.ajax({
                    url: App.url('/novosga.settings/servicos_unidade/') + servicoUnidade.servico.id,
                    type: 'delete',
                    success: () => {
                        this.loadServicosUnidade();
                    }
                });
            },

            updateServicoFromModal() {
                this.updateServico(this.servicoUnidade).then(() => {
                    this.loadServicosUnidade();
                    this.servicoUnidadeModal.hide();
                });
            },

            showModal(su) {
                this.servicoUnidade = Object.assign({}, su);
                this.servicoUnidadeModal.show();
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
                if (!confirm(desejaReiniciar)) {
                    return;
                }
                App.ajax({
                    url: App.url('/novosga.settings/reiniciar/') + servicoId,
                    type: 'post',
                    complete: () => {
                        this.loadContadores();
                    }
                });
            },

            limparSenhas: function () {
                if (!confirm(desejaLimparDados)) {
                    return;
                }
                App.ajax({
                    url: App.url('/novosga.settings/limpar'),
                    type: 'post',
                    complete: () => {
                        this.loadContadores();
                    }
                });
            },

            reiniciarSenhas: function () {
                if (!confirm(desejaReiniciarSenhas)) {
                    return;
                }

                App.ajax({
                    url: App.url('/novosga.settings/acumular_atendimentos'),
                    type: 'post',
                    complete: () => {
                        this.loadContadores();
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
                    }
                });
            },

            updateUsuario: function (usuario) {
                App.ajax({
                    url: App.url('/novosga.settings/usuario/') + usuario.id,
                    type: 'put',
                    data: {
                        tipoAtendimento: usuario.tipoAtendimento,
                        local: usuario.local,
                        numero: usuario.numero
                    }
                });
            },

            removeServicoUsuario: function (usuario, servicoUsuario) {
                App.ajax({
                    url: App.url('/novosga.settings/servico_usuario/') + usuario.id + '/' + servicoUsuario.id,
                    type: 'delete',
                    success: function () {
                        usuario.servicos.splice(usuario.servicos.indexOf(servicoUsuario), 1);
                    }
                });
            },

            updateServicoUsuario: function (usuario, servicoUsuario) {
                App.ajax({
                    url: App.url('/novosga.settings/servico_usuario/') + usuario.id + '/' + servicoUsuario.id,
                    type: 'put',
                    data: {
                        peso: servicoUsuario.peso,
                    },
                    success: function (response) {
                        servicoUsuario.peso = response.data.peso;
                    }
                });
            },
        },
        mounted() {
            this.servicosModal = new bootstrap.Modal(this.$refs.servicosModal);
            this.servicoUnidadeModal = new bootstrap.Modal(this.$refs.servicoUnidadeModal);

            App.SSE.connect([
                `/unidades/${this.unidade.id}/fila`,
            ]);

            App.SSE.onmessage = (e, data) => {
                this.loadContadores();
            };

            // ajax polling fallback
            App.SSE.ondisconnect = () => {
                this.loadContadores();
            };
            
            this.loadServicosUnidade();
            this.loadContadores();
        }
    });

})();
