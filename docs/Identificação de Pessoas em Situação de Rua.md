<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identificação de Pessoas em Situação de Rua - Aspectos Jurídicos e Operacionais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a5276;
            --secondary-color: #2874a6;
            --accent-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --info-color: #17a2b8;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.7;
            color: #2c3e50;
            background-color: #f8f9fa;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem 0;
        }
        
        .hero-section h1 {
            font-weight: 700;
            font-size: 2.2rem;
        }
        
        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid var(--accent-color);
            margin-bottom: 1.5rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            font-weight: 600;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem 1.25rem;
        }
        
        .card-header.bg-success {
            background: linear-gradient(135deg, #1e8449 0%, var(--success-color) 100%) !important;
        }
        
        .card-header.bg-info {
            background: linear-gradient(135deg, #117a8b 0%, var(--info-color) 100%) !important;
        }
        
        .card-header.bg-warning {
            background: linear-gradient(135deg, #d68910 0%, var(--warning-color) 100%) !important;
            color: #212529;
        }
        
        .legal-ref {
            background: #e8f4f8;
            border-left: 4px solid var(--accent-color);
            padding: 1rem 1.25rem;
            margin: 1rem 0;
            border-radius: 0 8px 8px 0;
            font-size: 0.95rem;
        }
        
        .legal-ref strong {
            color: var(--primary-color);
        }
        
        .flow-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .flow-number {
            background: var(--accent-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            margin-right: 1rem;
        }
        
        .flow-content {
            flex: 1;
        }
        
        .flow-content h5 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .doc-list {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .doc-list li {
            padding: 0.5rem 0;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .doc-list li:last-child {
            border-bottom: none;
        }
        
        .highlight-box {
            background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1.25rem;
            margin: 1rem 0;
        }
        
        .highlight-box.info {
            background: linear-gradient(135deg, #e7f3ff 0%, #cce5ff 100%);
            border-color: #004085;
        }
        
        .highlight-box.success {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-color: #28a745;
        }
        
        .table-legal {
            font-size: 0.9rem;
        }
        
        .table-legal th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .badge-law {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        .icon-box {
            width: 50px;
            height: 50px;
            background: var(--accent-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .toc-nav {
            position: sticky;
            top: 1rem;
        }
        
        .toc-nav .nav-link {
            color: var(--secondary-color);
            padding: 0.5rem 1rem;
            border-left: 2px solid transparent;
            font-size: 0.9rem;
        }
        
        .toc-nav .nav-link:hover,
        .toc-nav .nav-link.active {
            color: var(--primary-color);
            border-left-color: var(--accent-color);
            background: #e8f4f8;
        }
        
        .case-card {
            border-left: 4px solid var(--accent-color);
            transition: transform 0.2s;
        }
        
        .case-card:hover {
            transform: translateX(5px);
        }
        
        .principle-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .principle-item i {
            color: var(--success-color);
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }
        
        footer {
            background: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        @media print {
            .toc-nav, .no-print {
                display: none;
            }
            .card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body data-bs-spy="scroll" data-bs-target="#toc">

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1><i class="bi bi-person-badge me-2"></i>Identificação de Pessoas em Situação de Rua</h1>
                    <p class="lead mb-0">Aspectos Jurídicos, Marco Legal e Casos de Uso para Agentes Públicos</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <span class="badge bg-light text-dark fs-6">Atualizado: 2025</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container py-4">
        <div class="row">
            <!-- Table of Contents -->
            <div class="col-lg-3 d-none d-lg-block">
                <nav id="toc" class="toc-nav">
                    <h6 class="text-uppercase text-muted mb-3">Sumário</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="#marco-legal">Marco Legal</a>
                        <a class="nav-link" href="#identificacao-civil">Identificação Civil</a>
                        <a class="nav-link" href="#abordagem-social">Abordagem Social (SUAS)</a>
                        <a class="nav-link" href="#cadunico">Cadastro Único</a>
                        <a class="nav-link" href="#casos-uso">Casos de Uso</a>
                        <a class="nav-link" href="#fluxo-atendimento">Fluxo de Atendimento</a>
                        <a class="nav-link" href="#direitos-garantias">Direitos e Garantias</a>
                        <a class="nav-link" href="#inovacoes">Inovações Tecnológicas</a>
                    </nav>
                </nav>
            </div>

            <!-- Content -->
            <div class="col-lg-9">

                <!-- Seção 1: Marco Legal -->
                <section id="marco-legal" class="mb-5">
                    <h2 class="section-title"><i class="bi bi-journal-text me-2"></i>Marco Legal</h2>
                    
                    <p>A identificação de pessoas em situação de rua no Brasil está fundamentada em um conjunto de normas que equilibram o direito à assistência social com a proteção da dignidade humana e dos dados pessoais.</p>
                    
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-building me-2"></i>Principais Normativos
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-legal table-hover">
                                    <thead>
                                        <tr>
                                            <th>Norma</th>
                                            <th>Conteúdo</th>
                                            <th>Relevância</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge bg-primary badge-law">CF/1988</span><br>Art. 5º, LVIII</td>
                                            <td>O civilmente identificado não será submetido a identificação criminal</td>
                                            <td>Fundamento constitucional</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-success badge-law">Decreto 7.053/2009</span></td>
                                            <td>Política Nacional para População em Situação de Rua</td>
                                            <td>Define conceito e diretrizes</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-info badge-law">Resolução CNAS 109/2009</span></td>
                                            <td>Tipificação Nacional dos Serviços Socioassistenciais</td>
                                            <td>Regulamenta Abordagem Social</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-warning text-dark badge-law">Lei 12.037/2009</span></td>
                                            <td>Identificação Criminal do Civilmente Identificado</td>
                                            <td>Documentos válidos para ID</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-secondary badge-law">Lei 13.444/2017</span></td>
                                            <td>Identificação Civil Nacional (ICN)</td>
                                            <td>CPF como identificador único</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-danger badge-law">Lei 13.709/2018</span></td>
                                            <td>Lei Geral de Proteção de Dados (LGPD)</td>
                                            <td>Proteção de dados pessoais</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="legal-ref">
                        <strong><i class="bi bi-quote me-2"></i>Decreto 7.053/2009, Art. 1º, Parágrafo único:</strong><br>
                        "Considera-se população em situação de rua o grupo populacional heterogêneo que possui em comum a <strong>pobreza extrema</strong>, os <strong>vínculos familiares interrompidos ou fragilizados</strong> e a <strong>inexistência de moradia convencional regular</strong>, e que utiliza os logradouros públicos e as áreas degradadas como espaço de moradia e de sustento, de forma temporária ou permanente, bem como as unidades de acolhimento para pernoite temporário ou como moradia provisória."
                    </div>
                </section>

                <!-- Seção 2: Identificação Civil -->
                <section id="identificacao-civil" class="mb-5">
                    <h2 class="section-title"><i class="bi bi-person-vcard me-2"></i>Identificação Civil</h2>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-success">
                                    <i class="bi bi-check-circle me-2"></i>Documentos Válidos (Lei 12.037/2009)
                                </div>
                                <div class="card-body">
                                    <ul class="doc-list list-unstyled mb-0">
                                        <li><i class="bi bi-credit-card-2-front text-primary me-2"></i>Carteira de Identidade (RG)</li>
                                        <li><i class="bi bi-briefcase text-primary me-2"></i>Carteira de Trabalho (CTPS)</li>
                                        <li><i class="bi bi-award text-primary me-2"></i>Carteira Profissional</li>
                                        <li><i class="bi bi-globe text-primary me-2"></i>Passaporte</li>
                                        <li><i class="bi bi-person-badge text-primary me-2"></i>Carteira de Identificação Funcional</li>
                                        <li><i class="bi bi-card-text text-primary me-2"></i>Outro documento público com foto</li>
                                        <li><i class="bi bi-shield-check text-primary me-2"></i>Documentos de identificação militares</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-info">
                                    <i class="bi bi-info-circle me-2"></i>Para Cadastro Único (CadÚnico)
                                </div>
                                <div class="card-body">
                                    <p class="mb-3"><strong>Responsável Familiar deve apresentar:</strong></p>
                                    <ul class="doc-list list-unstyled mb-3">
                                        <li><i class="bi bi-hash text-info me-2"></i>CPF <span class="badge bg-danger">Obrigatório</span></li>
                                        <li><i class="bi bi-card-checklist text-info me-2"></i>Título de Eleitor <span class="badge bg-secondary">Alternativo</span></li>
                                    </ul>
                                    <div class="highlight-box info">
                                        <i class="bi bi-lightbulb me-2"></i>
                                        <strong>Importante:</strong> Pessoas em situação de rua sem documentos devem ser orientadas sobre como obtê-los. Todo cidadão tem direito à documentação civil.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="highlight-box success mt-4">
                        <h5><i class="bi bi-shield-check me-2"></i>Princípio Constitucional</h5>
                        <p class="mb-0">A identificação civil é <strong>suficiente</strong> para todos os fins legais. A identificação criminal só pode ocorrer nas hipóteses expressamente previstas em lei (Art. 5º, LVIII, CF/88).</p>
                    </div>
                </section>

                <!-- Seção 3: Abordagem Social -->
                <section id="abordagem-social" class="mb-5">
                    <h2 class="section-title"><i class="bi bi-people me-2"></i>Serviço Especializado em Abordagem Social</h2>
                    
                    <p>A Abordagem Social é um serviço da <strong>Proteção Social Especial de Média Complexidade</strong> do SUAS, regulamentado pela Resolução CNAS nº 109/2009.</p>
                    
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-clipboard-data me-2"></i>Características do Serviço
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="text-primary"><i class="bi bi-bullseye me-2"></i>Finalidade</h5>
                                    <p>Assegurar trabalho social de abordagem e busca ativa que identifique, nos territórios, a incidência de:</p>
                                    <ul>
                                        <li>Trabalho infantil</li>
                                        <li>Exploração sexual de crianças e adolescentes</li>
                                        <li>Situação de rua</li>
                                        <li>Outras violações de direitos</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="text-primary"><i class="bi bi-geo-alt me-2"></i>Locais de Atuação</h5>
                                    <ul>
                                        <li>Praças e espaços públicos</li>
                                        <li>Entroncamentos de estradas e fronteiras</li>
                                        <li>Locais de atividades laborais informais</li>
                                        <li>Terminais de transporte (ônibus, metrô, trem)</li>
                                        <li>Áreas de intensa circulação de pessoas</li>
                                        <li>Locais de comércio</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="icon-box mx-auto">
                                            <i class="bi bi-clock"></i>
                                        </div>
                                        <h6>Funcionamento</h6>
                                        <p class="small text-muted">Mínimo 5 dias/semana<br>8 horas diárias<br>Possibilidade de feriados e finais de semana</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="icon-box mx-auto" style="background: var(--success-color);">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <h6>Unidade de Referência</h6>
                                        <p class="small text-muted">Centro POP<br>CREAS<br>Unidades específicas referenciadas</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="icon-box mx-auto" style="background: var(--warning-color);">
                                            <i class="bi bi-diagram-3"></i>
                                        </div>
                                        <h6>Abrangência</h6>
                                        <p class="small text-muted">Municipal e/ou Regional<br>Articulação intersetorial</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>Princípios da Abordagem
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="principle-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Urbanidade e respeito à dignidade humana</span>
                                    </div>
                                    <div class="principle-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Identificação funcional visível do agente</span>
                                    </div>
                                    <div class="principle-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Critérios objetivos para abordagem</span>
                                    </div>
                                    <div class="principle-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Escuta qualificada e acolhimento</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="principle-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Sigilo das informações coletadas</span>
                                    </div>
                                    <div class="principle-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Orientação sobre direitos e serviços</span>
                                    </div>
                                    <div class="principle-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Encaminhamento para rede de proteção</span>
                                    </div>
                                    <div class="principle-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Respeito à autonomia do indivíduo</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Seção 4: Cadastro Único -->
                <section id="cadunico" class="mb-5">
                    <h2 class="section-title"><i class="bi bi-database me-2"></i>Cadastro Único (CadÚnico)</h2>
                    
                    <p>O Cadastro Único é o principal instrumento do Estado brasileiro para identificação e caracterização socioeconômica das famílias de baixa renda, incluindo pessoas em situação de rua.</p>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <i class="bi bi-file-earmark-text me-2"></i>Formulário Suplementar 2 - Pessoa em Situação de Rua
                                </div>
                                <div class="card-body">
                                    <h6 class="text-primary">Informações Coletadas:</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="small">
                                                <li>Dados de identificação pessoal</li>
                                                <li>Tempo em situação de rua</li>
                                                <li>Motivos da situação de rua</li>
                                                <li>Trajetória de vida</li>
                                                <li>Vínculos familiares</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul class="small">
                                                <li>Condições de saúde</li>
                                                <li>Uso de substâncias psicoativas</li>
                                                <li>Acesso a serviços</li>
                                                <li>Renda e trabalho</li>
                                                <li>Necessidades específicas</li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <h6 class="text-primary">Especificidades do Cadastramento:</h6>
                                    <ul class="small mb-0">
                                        <li><strong>Endereço de referência:</strong> Pode ser de equipamento da rede socioassistencial (Centro POP, CREAS, CRAS, Unidade de Acolhimento)</li>
                                        <li><strong>Responsável familiar:</strong> A própria pessoa pode ser RF, mesmo em família unipessoal</li>
                                        <li><strong>Documentação mínima:</strong> CPF ou Título de Eleitor</li>
                                        <li><strong>Atualização:</strong> Deve ser realizada a cada 2 anos ou quando houver mudança na situação</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary"><i class="bi bi-gift me-2"></i>Programas Acessíveis via CadÚnico</h6>
                                    <ul class="small mb-0">
                                        <li>Bolsa Família</li>
                                        <li>BPC (quando elegível)</li>
                                        <li>Tarifa Social de Energia</li>
                                        <li>Auxílio Gás</li>
                                        <li>Minha Casa Minha Vida</li>
                                        <li>PRONATEC</li>
                                        <li>Isenção em concursos</li>
                                        <li>Carteira do Idoso</li>
                                        <li>ID Jovem</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="highlight-box mt-3">
                                <h6><i class="bi bi-key me-2"></i>NIS</h6>
                                <p class="small mb-0">O <strong>Número de Identificação Social</strong> é atribuído a cada pessoa cadastrada, permitindo sua identificação única em todos os sistemas do governo federal.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Seção 5: Casos de Uso -->
                <section id="casos-uso" class="mb-5">
                    <h2 class="section-title"><i class="bi bi-diagram-2 me-2"></i>Casos de Uso da Identificação</h2>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card case-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">
                                        <i class="bi bi-heart-pulse me-2"></i>Atendimento em Saúde
                                    </h5>
                                    <p class="card-text">Consultórios na Rua e UBS podem atender pessoas sem documentos, utilizando:</p>
                                    <ul class="small">
                                        <li>Cadastro provisório no sistema de saúde</li>
                                        <li>Encaminhamento para obtenção de CNS</li>
                                        <li>Vinculação ao território de referência</li>
                                    </ul>
                                    <span class="badge bg-success">Não exige documento para atendimento de urgência</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card case-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">
                                        <i class="bi bi-house-door me-2"></i>Acolhimento Institucional
                                    </h5>
                                    <p class="card-text">Serviços de acolhimento realizam identificação para:</p>
                                    <ul class="small">
                                        <li>Registro de entrada e permanência</li>
                                        <li>Construção de Plano Individual de Atendimento</li>
                                        <li>Articulação com rede de serviços</li>
                                    </ul>
                                    <span class="badge bg-info">Falta de documentos não impede acolhimento</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card case-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">
                                        <i class="bi bi-cash-stack me-2"></i>Acesso a Benefícios
                                    </h5>
                                    <p class="card-text">Identificação necessária para:</p>
                                    <ul class="small">
                                        <li>Inscrição no Cadastro Único</li>
                                        <li>Concessão de benefícios eventuais</li>
                                        <li>Acesso ao Bolsa Família e outros programas</li>
                                    </ul>
                                    <span class="badge bg-warning text-dark">Exige CPF ou Título de Eleitor</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card case-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">
                                        <i class="bi bi-search me-2"></i>Busca de Pessoas Desaparecidas
                                    </h5>
                                    <p class="card-text">Identificação permite:</p>
                                    <ul class="small">
                                        <li>Cruzamento com bancos de desaparecidos</li>
                                        <li>Reconexão com familiares</li>
                                        <li>Articulação com órgãos de segurança</li>
                                    </ul>
                                    <span class="badge bg-secondary">Respeito à vontade do indivíduo</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card case-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">
                                        <i class="bi bi-briefcase me-2"></i>Inclusão Produtiva
                                    </h5>
                                    <p class="card-text">Documentação necessária para:</p>
                                    <ul class="small">
                                        <li>Inscrição em cursos de qualificação</li>
                                        <li>Acesso a programas de emprego</li>
                                        <li>Formalização de trabalho</li>
                                    </ul>
                                    <span class="badge bg-primary">Exige documentação completa</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card case-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">
                                        <i class="bi bi-clipboard2-pulse me-2"></i>Monitoramento de Políticas
                                    </h5>
                                    <p class="card-text">Dados de identificação permitem:</p>
                                    <ul class="small">
                                        <li>Contagem oficial da população</li>
                                        <li>Perfil sociodemográfico</li>
                                        <li>Avaliação de efetividade das políticas</li>
                                    </ul>
                                    <span class="badge bg-dark">Dados agregados e anonimizados</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Seção 6: Fluxo de Atendimento -->
                <section id="fluxo-atendimento" class="mb-5">
                    <h2 class="section-title"><i class="bi bi-signpost-split me-2"></i>Fluxo de Atendimento para Identificação</h2>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="flow-step">
                                <div class="flow-number">1</div>
                                <div class="flow-content">
                                    <h5>Abordagem Inicial</h5>
                                    <p class="mb-0">Equipe de Abordagem Social realiza contato nos territórios, com postura acolhedora e respeito à autonomia. Apresenta-se identificada e explica o objetivo do serviço.</p>
                                </div>
                            </div>
                            
                            <div class="flow-step">
                                <div class="flow-number">2</div>
                                <div class="flow-content">
                                    <h5>Escuta Qualificada</h5>
                                    <p class="mb-0">Realiza escuta das demandas, necessidades e trajetória de vida. Identifica situação documental e de acesso a serviços.</p>
                                </div>
                            </div>
                            
                            <div class="flow-step">
                                <div class="flow-number">3</div>
                                <div class="flow-content">
                                    <h5>Verificação Documental</h5>
                                    <p class="mb-0"><strong>Com documentos:</strong> Registra dados e encaminha para serviços.<br>
                                    <strong>Sem documentos:</strong> Orienta sobre obtenção e encaminha para documentação civil.</p>
                                </div>
                            </div>
                            
                            <div class="flow-step">
                                <div class="flow-number">4</div>
                                <div class="flow-content">
                                    <h5>Encaminhamento para Documentação</h5>
                                    <p class="mb-0">Articulação com órgãos emissores (Receita Federal para CPF, Cartórios para certidões, Institutos de Identificação para RG). Muitos municípios realizam mutirões de documentação.</p>
                                </div>
                            </div>
                            
                            <div class="flow-step">
                                <div class="flow-number">5</div>
                                <div class="flow-content">
                                    <h5>Cadastramento</h5>
                                    <p class="mb-0">Com CPF ou Título de Eleitor, realiza-se inscrição no Cadastro Único, utilizando o Formulário Suplementar 2 para pessoas em situação de rua.</p>
                                </div>
                            </div>
                            
                            <div class="flow-step">
                                <div class="flow-number">6</div>
                                <div class="flow-content">
                                    <h5>Acompanhamento</h5>
                                    <p class="mb-0">Centro POP ou CREAS realiza acompanhamento sistemático, atualiza cadastro e articula acesso a políticas públicas.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Seção 7: Direitos e Garantias -->
                <section id="direitos-garantias" class="mb-5">
                    <h2 class="section-title"><i class="bi bi-shield-check me-2"></i>Direitos e Garantias na Abordagem</h2>
                    
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header bg-success">
                                    <i class="bi bi-hand-thumbs-up me-2"></i>O Agente Público DEVE
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li>Estar devidamente identificado (crachá visível)</li>
                                        <li>Agir com urbanidade e respeito</li>
                                        <li>Informar o motivo e objetivo da abordagem</li>
                                        <li>Respeitar a autonomia e vontade da pessoa</li>
                                        <li>Garantir sigilo das informações coletadas</li>
                                        <li>Orientar sobre direitos e serviços disponíveis</li>
                                        <li>Respeitar identidade de gênero (abordagem por agente do gênero adequado)</li>
                                        <li>Registrar apenas informações necessárias ao atendimento</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header" style="background: linear-gradient(135deg, #922b21 0%, #c0392b 100%);">
                                    <i class="bi bi-hand-thumbs-down me-2"></i>O Agente Público NÃO PODE
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li>Realizar abordagem sem critérios objetivos</li>
                                        <li>Apreender documentos ou pertences sem base legal</li>
                                        <li>Forçar remoção ou deslocamento</li>
                                        <li>Realizar ações vexatórias ou humilhantes</li>
                                        <li>Condicionar atendimento à apresentação de documentos</li>
                                        <li>Compartilhar dados sem autorização ou base legal</li>
                                        <li>Discriminar por aparência, origem ou condição</li>
                                        <li>Utilizar força desproporcional ou coerção</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="legal-ref mt-4">
                        <strong><i class="bi bi-gavel me-2"></i>ADPF 976 (STF):</strong><br>
                        O Supremo Tribunal Federal reforçou a vedação a remoções forçadas e apreensão de pertences de pessoas em situação de rua sem prévia ordem judicial, determinando a observância das diretrizes da Política Nacional (Decreto 7.053/2009).
                    </div>
                </section>

                <!-- Seção 8: Inovações Tecnológicas -->
                <section id="inovacoes" class="mb-5">
                    <h2 class="section-title"><i class="bi bi-cpu me-2"></i>Inovações Tecnológicas na Identificação</h2>
                    
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-map me-2"></i>Exemplo: Santa Catarina - Lei Estadual 19.380/2025
                        </div>
                        <div class="card-body">
                            <p>Santa Catarina instituiu o <strong>Cadastro Estadual de Pessoas em Situação de Rua</strong>, integrando tecnologias de identificação:</p>
                            
                            <div class="row">
                                <div class="col-md-4 text-center mb-3">
                                    <div class="icon-box mx-auto">
                                        <i class="bi bi-fingerprint"></i>
                                    </div>
                                    <h6>Biometria</h6>
                                    <p class="small text-muted">Coleta de dados biométricos para identificação única</p>
                                </div>
                                <div class="col-md-4 text-center mb-3">
                                    <div class="icon-box mx-auto" style="background: var(--success-color);">
                                        <i class="bi bi-person-bounding-box"></i>
                                    </div>
                                    <h6>Reconhecimento Facial</h6>
                                    <p class="small text-muted">Fotos para identificação e prontuário digital</p>
                                </div>
                                <div class="col-md-4 text-center mb-3">
                                    <div class="icon-box mx-auto" style="background: var(--warning-color);">
                                        <i class="bi bi-geo-alt"></i>
                                    </div>
                                    <h6>Georreferenciamento</h6>
                                    <p class="small text-muted">Localização para acompanhamento e encaminhamentos</p>
                                </div>
                            </div>
                            
                            <div class="highlight-box">
                                <h6><i class="bi bi-shield-lock me-2"></i>Garantias de Proteção de Dados</h6>
                                <ul class="small mb-0">
                                    <li>Acesso restrito a profissionais autorizados</li>
                                    <li>Níveis de restrição conforme a função</li>
                                    <li>Integração com CadÚnico federal</li>
                                    <li>Sigilo das informações pessoais</li>
                                    <li>Finalidade exclusiva de atendimento e políticas públicas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header bg-info">
                            <i class="bi bi-phone me-2"></i>Aplicativos e Sistemas Digitais
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Meu CadÚnico</h6>
                                    <p class="small">Aplicativo oficial para consulta e acompanhamento do cadastro, disponível para Android e iOS.</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Prontuário SUAS</h6>
                                    <p class="small">Sistema informatizado para registro de atendimentos e acompanhamento familiar nos serviços socioassistenciais.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Referências -->
                <section class="mb-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-book me-2"></i>Referências Normativas
                        </div>
                        <div class="card-body">
                            <ul class="small mb-0">
                                <li>Constituição Federal de 1988, Art. 5º, LVIII</li>
                                <li>Lei nº 8.742/1993 - Lei Orgânica da Assistência Social (LOAS)</li>
                                <li>Decreto nº 7.053/2009 - Política Nacional para População em Situação de Rua</li>
                                <li>Resolução CNAS nº 109/2009 - Tipificação Nacional dos Serviços Socioassistenciais</li>
                                <li>Lei nº 12.037/2009 - Identificação Criminal do Civilmente Identificado</li>
                                <li>Lei nº 13.444/2017 - Identificação Civil Nacional</li>
                                <li>Lei nº 13.709/2018 - Lei Geral de Proteção de Dados</li>
                                <li>Guia de Cadastramento de Pessoas em Situação de Rua - MDS</li>
                                <li>Guia Ministerial CNMP - Defesa dos Direitos das Pessoas em Situação de Rua</li>
                            </ul>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h5>Identificação de Pessoas em Situação de Rua</h5>
                    <p class="small mb-0">Documento técnico para orientação de agentes públicos sobre aspectos jurídicos e operacionais da identificação e cadastramento de pessoas em situação de rua no Brasil.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="small mb-0">Atualizado em 2025</p>
                    <p class="small mb-0">Baseado na legislação vigente</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>