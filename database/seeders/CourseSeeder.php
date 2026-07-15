<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Support\LessonCodeValidator;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        Enrollment::query()->delete();
        Lesson::query()->delete();
        Section::query()->delete();
        Course::query()->delete();

        $courses = [
            [
                'title' => 'Fundamentos de HTML + CSS',
                'subtitle' => 'Curso estruturado a partir da apostila de HTML e CSS: teoria, exemplos e exercicios praticos.',
                'instructor' => 'Prof. Msc. Regilan Meira Silva',
                'rating' => 4.9,
                'review_count' => 10420,
                'student_count' => 42760,
                'price' => 1990.00,
                'original_price' => 7800.00,
                'image' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=600&h=400&fit=crop',
                'category' => 'Desenvolvimento Web',
                'level' => 'Iniciante',
                'total_hours' => 32,
                'description' => 'Percurso completo de fundamentos de HTML e CSS baseado no roteiro da apostila, cobrindo tags basicas, imagens, links, tabelas, box-model, posicionamento, layout e formularios.',
                'sections' => [
                    [
                        'title' => '1. Introducao a Web e ao HTML/CSS',
                        'lessons' => [
                            [
                                'title' => 'Hipertexto, hipermidia e papel do HTML/CSS',
                                'duration' => '10:00',
                                'is_free' => true,
                                'type' => 'text',
                                'content' => "Nesta licao vais entender a ideia de hipertexto, hipermidia e como uma pagina web e formada por HTML para estrutura e CSS para apresentacao.\n\nPontos-chave:\n- Como browser interpreta codigo.\n- Diferenca entre conteudo e estilo.\n- Porque separar HTML e CSS melhora manutencao.",
                                'video_url' => 'https://www.youtube.com/watch?v=UB1O30fR-EE',
                            ],
                            [
                                'title' => 'Estrutura minima de um documento HTML',
                                'duration' => '14:30',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "Uma pagina precisa de estrutura base para funcionar bem em qualquer browser.\n\nRequisitos:\n1. Adicionar as tags html, head e body.\n2. Definir uma tag title com o nome da pagina.\n3. Criar um h1 e um paragrafo dentro do body.",
                                'html_code' => "<h1>Minha primeira pagina</h1>\n<p>Este conteudo ainda precisa da estrutura completa do documento.</p>",
                                'css_code' => "body {\n  font-family: Arial, sans-serif;\n  margin: 20px;\n  line-height: 1.5;\n}",
                                'js_code' => "console.log('Documento inicial carregado');",
                            ],
                            [
                                'title' => 'Checkpoint: Fundamentos de introducao',
                                'duration' => '07:10',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Valida os conceitos introdutorios de HTML, CSS e estrutura de pagina.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'intro-q1',
                                        'question' => 'Qual linguagem define a estrutura de uma pagina web?',
                                        'options' => ['HTML', 'CSS', 'SQL', 'PNG'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'intro-q2',
                                        'question' => 'Qual linguagem e usada para apresentacao visual?',
                                        'options' => ['CSS', 'HTML', 'XML', 'CSV'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'intro-q3',
                                        'question' => 'O elemento title fica dentro de qual area do documento?',
                                        'options' => ['head', 'footer', 'main', 'nav'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'intro-q4',
                                        'question' => 'Separar HTML e CSS ajuda principalmente em...',
                                        'options' => ['manutencao e reutilizacao', 'aumentar tamanho do arquivo', 'impedir responsividade', 'eliminar tags'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'intro-q5',
                                        'question' => 'Uma pagina web e composta por...',
                                        'options' => ['arquivos de codigo e recursos (imagem, audio, etc.)', 'apenas texto puro', 'somente JavaScript', 'apenas CSS'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => '2. Primeiros elementos HTML: paragrafos, cabecalhos e listas',
                        'lessons' => [
                            [
                                'title' => 'Paragrafos, cabecalhos e quebra de linha',
                                'duration' => '15:00',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "Nesta parte da apostila, o foco e marcar conteudo textual corretamente usando tags basicas.\n\nRequisitos:\n1. Criar um h1 e um h2 com titulos diferentes.\n2. Criar dois paragrafos usando a tag p.\n3. Usar br para quebrar linha dentro de um paragrafo.",
                                'html_code' => "<h1></h1>\n<h2></h2>\n<p></p>\n<p></p>",
                                'css_code' => "body {\n  font-family: Arial, sans-serif;\n}",
                                'js_code' => "console.log('Treino de elementos textuais HTML');",
                            ],
                            [
                                'title' => 'Listas ordenadas e nao ordenadas',
                                'duration' => '14:20',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "Listas sao essenciais para apresentar sequencias e itens relacionados.\n\nRequisitos:\n1. Criar uma lista ordenada com 4 passos.\n2. Criar uma lista nao ordenada com 4 itens.\n3. Garantir que cada item esteja dentro de li.",
                                'html_code' => "<h2>Checklist de instalacao</h2>\n<ol>\n</ol>\n\n<h2>Recursos necessarios</h2>\n<ul>\n</ul>",
                                'css_code' => "body {\n  font-family: Arial, sans-serif;\n  margin: 20px;\n}",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Checkpoint: tags basicas de conteudo',
                                'duration' => '07:20',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Verifica dominio de paragrafos, cabecalhos e listas.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'el-q1',
                                        'question' => 'Qual tag representa um paragrafo?',
                                        'options' => ['<p>', '<h1>', '<li>', '<ul>'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'el-q2',
                                        'question' => 'Cabecalhos HTML variam de...',
                                        'options' => ['h1 a h6', 'h1 a h3', 'h0 a h6', 'h2 a h8'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'el-q3',
                                        'question' => 'Qual tag cria lista ordenada?',
                                        'options' => ['<ol>', '<ul>', '<dl>', '<li>'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'el-q4',
                                        'question' => 'Qual tag cria lista nao ordenada?',
                                        'options' => ['<ul>', '<ol>', '<tr>', '<th>'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'el-q5',
                                        'question' => 'Cada item da lista deve usar...',
                                        'options' => ['<li>', '<item>', '<list>', '<td>'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => '3. Imagens e nocoes de CSS (fontes e background)',
                        'lessons' => [
                            [
                                'title' => 'Inserir imagens com a tag img',
                                'duration' => '13:30',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "Imagens sao adicionadas com a tag img e o atributo src.\n\nRequisitos:\n1. Inserir uma imagem com src valido.\n2. Definir texto alternativo no atributo alt.\n3. Definir largura da imagem com atributo width.",
                                'html_code' => "<h2>Galeria</h2>\n<p>Adiciona uma imagem abaixo:</p>",
                                'css_code' => "body {\n  font-family: Arial, sans-serif;\n  margin: 20px;\n}",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Primeiras regras CSS: fonte e background',
                                'duration' => '16:00',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'css',
                                'content' => "CSS permite definir tipografia e fundo sem alterar o HTML.\n\nRequisitos:\n1. Alterar font-family e font-size do body.\n2. Aplicar color em h1 e p.\n3. Aplicar background-color no body e background-image no header.",
                                'html_code' => "<header class=\"hero\">\n  <h1>Apostila HTML e CSS</h1>\n  <p>Treinar propriedades de fonte e background.</p>\n</header>",
                                'css_code' => ".hero {\n  padding: 20px;\n}\n",
                                'js_code' => "console.log('Configura fonte e background com CSS');",
                            ],
                            [
                                'title' => 'Checkpoint: imagens e estilos iniciais',
                                'duration' => '06:40',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Confirma os conceitos de img, regras CSS e backgrounds.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'imgcss-q1',
                                        'question' => 'Qual atributo define o caminho da imagem?',
                                        'options' => ['src', 'href', 'alt', 'title'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'imgcss-q2',
                                        'question' => 'Para acessibilidade, a imagem deve ter...',
                                        'options' => ['alt', 'id', 'class', 'name'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'imgcss-q3',
                                        'question' => 'Uma regra CSS e composta por...',
                                        'options' => ['seletor, propriedade e valor', 'somente seletor', 'somente valor', 'apenas atributo HTML'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'imgcss-q4',
                                        'question' => 'Qual propriedade muda a cor de fundo?',
                                        'options' => ['background-color', 'font-style', 'line-height', 'text-indent'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'imgcss-q5',
                                        'question' => 'Qual propriedade define a familia tipografica?',
                                        'options' => ['font-family', 'text-shadow', 'opacity', 'display'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => '4. Links, tabelas e box-model',
                        'lessons' => [
                            [
                                'title' => 'Criar links internos e externos',
                                'duration' => '13:20',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "A apostila cobre links absolutos, relativos e ancoras.\n\nRequisitos:\n1. Criar um link externo com target _blank.\n2. Criar um link relativo para pagina interna.\n3. Criar uma ancora para navegar na mesma pagina.",
                                'html_code' => "<h1 id=\"topo\">Portal de Estudos</h1>\n<p>Completa os links abaixo:</p>",
                                'css_code' => "body { font-family: Arial, sans-serif; margin: 20px; }\na { color: #1d4ed8; }",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Montar tabela HTML com cabecalho',
                                'duration' => '15:40',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "Tabelas apresentam dados em linhas e colunas.\n\nRequisitos:\n1. Criar table com thead e tbody.\n2. Adicionar 3 colunas no cabecalho.\n3. Inserir pelo menos 3 linhas de dados.",
                                'html_code' => "<h2>Horario semanal</h2>\n<table border=\"1\">\n</table>",
                                'css_code' => "table {\n  border-collapse: collapse;\n}\n",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Aplicar margin, border e padding em um bloco',
                                'duration' => '16:30',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'css',
                                'content' => "No box-model, margin, border e padding controlam espacos e limites do elemento.\n\nRequisitos:\n1. Definir margin de 20px no bloco .box.\n2. Definir padding interno de 16px.\n3. Aplicar border visivel com espessura e cor.",
                                'html_code' => "<div class=\"box\">\n  <h3>Resumo da Aula</h3>\n  <p>Treino de box-model com CSS.</p>\n</div>",
                                'css_code' => ".box {\n  background: #f8fafc;\n}\n",
                                'js_code' => "console.log('Treino de box-model');",
                            ],
                            [
                                'title' => 'Checkpoint: links, tabelas e box-model',
                                'duration' => '07:30',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Avalia os conceitos principais do capitulo 4 da apostila.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'linktab-q1',
                                        'question' => 'Qual tag cria um link?',
                                        'options' => ['<a>', '<link>', '<href>', '<src>'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'linktab-q2',
                                        'question' => 'Qual tag representa uma linha de tabela?',
                                        'options' => ['<tr>', '<td>', '<th>', '<tb>'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'linktab-q3',
                                        'question' => 'Qual propriedade define espaco interno?',
                                        'options' => ['padding', 'margin', 'border', 'outline'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'linktab-q4',
                                        'question' => 'Qual propriedade define espaco externo?',
                                        'options' => ['margin', 'padding', 'height', 'font-size'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'linktab-q5',
                                        'question' => 'Para abrir link em nova aba, usa-se...',
                                        'options' => ['target=\"_blank\"', 'target=\"_self\"', 'rel=\"table\"', 'href=\"#newtab\"'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => '5. Estilizacao de textos, listas e pseudo-elementos',
                        'lessons' => [
                            [
                                'title' => 'Estilizacao tipografica com classes',
                                'duration' => '14:30',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'css',
                                'content' => "Classes CSS ajudam a reaplicar estilos em varios elementos.\n\nRequisitos:\n1. Criar classe .destaque para titulo e paragrafo.\n2. Alterar font-weight, text-transform e color.\n3. Definir line-height para melhorar leitura.",
                                'html_code' => "<h1 class=\"destaque\">Noticias da Semana</h1>\n<p class=\"destaque\">Texto de exemplo para estilizacao.</p>\n<ul>\n  <li>Item A</li>\n  <li>Item B</li>\n</ul>",
                                'css_code' => ".destaque {\n}\n",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Pseudo-classes e pseudo-elementos',
                                'duration' => '15:20',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'css',
                                'content' => "Pseudo-classes e pseudo-elementos adicionam comportamento visual sem mudar o HTML.\n\nRequisitos:\n1. Aplicar :hover em links.\n2. Aplicar :first-child em lista.\n3. Aplicar ::first-letter no primeiro paragrafo.",
                                'html_code' => "<p class=\"intro\">Este paragrafo sera usado para pseudo-elementos.</p>\n<ul class=\"topicos\">\n  <li>HTML</li>\n  <li>CSS</li>\n  <li>Layout</li>\n</ul>\n<a href=\"#\">Ler mais</a>",
                                'css_code' => ".intro {\n  max-width: 55ch;\n}\n",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Checkpoint: estilizacao e pseudo-elementos',
                                'duration' => '07:00',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Valida dominio sobre classes, listas e pseudo-seletores.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'style-q1',
                                        'question' => 'Para selecionar classe em CSS, usa-se...',
                                        'options' => ['.nomeClasse', '#nomeClasse', 'nomeClasse()', '@nomeClasse'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'style-q2',
                                        'question' => 'Qual seletor aplica estilo no primeiro filho?',
                                        'options' => [':first-child', ':hover', '::after', ':focus-visible'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'style-q3',
                                        'question' => 'Qual pseudo-elemento estiliza a primeira letra?',
                                        'options' => ['::first-letter', ':first-letter', '::letter-first', ':first'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'style-q4',
                                        'question' => 'Qual propriedade transforma texto em maiusculas?',
                                        'options' => ['text-transform', 'font-family', 'font-style', 'letter-space'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'style-q5',
                                        'question' => 'Qual pseudo-classe reage ao rato por cima do link?',
                                        'options' => [':hover', ':active-link', ':visited-link', ':target-self'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => '6. Posicionamento e layout com CSS',
                        'lessons' => [
                            [
                                'title' => 'Posicionamento relativo e absoluto',
                                'duration' => '16:10',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'css',
                                'content' => "Posicionamento controla como elementos ocupam a pagina.\n\nRequisitos:\n1. Definir container com position relative.\n2. Posicionar badge com position absolute no canto superior.\n3. Ajustar top e right da badge.",
                                'html_code' => "<div class=\"card\">\n  <span class=\"badge\">Novo</span>\n  <h3>Curso HTML + CSS</h3>\n  <p>Aprendizagem pratica.</p>\n</div>",
                                'css_code' => ".card {\n  width: 320px;\n  border: 1px solid #cbd5e1;\n  border-radius: 10px;\n  padding: 16px;\n}\n\n.badge {\n  background: #111827;\n  color: #fff;\n  padding: 2px 8px;\n  border-radius: 999px;\n}\n",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Layout de duas colunas',
                                'duration' => '17:00',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'css',
                                'content' => "Construir layout e parte central do capitulo de posicionamento e layout.\n\nRequisitos:\n1. Criar layout com sidebar e conteudo principal.\n2. Definir larguras proporcionais das colunas.\n3. Ajustar para 1 coluna em ecras pequenos com media query.",
                                'html_code' => "<div class=\"layout\">\n  <aside class=\"sidebar\">Menu</aside>\n  <main class=\"content\">Conteudo da pagina</main>\n</div>",
                                'css_code' => ".layout {\n}\n\n.sidebar {\n  background: #e2e8f0;\n  padding: 12px;\n}\n\n.content {\n  background: #f8fafc;\n  padding: 12px;\n}\n",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Checkpoint: posicionamento e layout',
                                'duration' => '07:10',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Testa conhecimento de position e composicao de layout.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'layout-q1',
                                        'question' => 'Para posicionar filho absoluto em relacao ao pai, o pai deve ter...',
                                        'options' => ['position: relative', 'display: none', 'float: right', 'overflow: hidden'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'layout-q2',
                                        'question' => 'Qual valor de position retira elemento do fluxo normal?',
                                        'options' => ['absolute', 'static', 'inherit', 'initial-only'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'layout-q3',
                                        'question' => 'Media query e usada para...',
                                        'options' => ['adaptar layout a diferentes larguras', 'criar tabelas', 'validar formulario', 'inserir audio'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'layout-q4',
                                        'question' => 'Qual propriedade ajuda organizar colunas modernas?',
                                        'options' => ['display: grid', 'text-transform', 'font-variant', 'z-index only'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'layout-q5',
                                        'question' => 'top/right funcionam principalmente com...',
                                        'options' => ['positioned elements', 'elementos estaticos sem position', 'somente body', 'somente img'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => '7. Formularios web',
                        'lessons' => [
                            [
                                'title' => 'Criar estrutura de formulario',
                                'duration' => '15:10',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "Formularios capturam dados do utilizador com campos especificos.\n\nRequisitos:\n1. Criar form com campos nome, email e mensagem.\n2. Associar labels a cada input.\n3. Adicionar botao submit.",
                                'html_code' => "<h2>Contacto</h2>\n<form>\n</form>",
                                'css_code' => "form {\n  max-width: 460px;\n  display: grid;\n  gap: 10px;\n}\n",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Estilizar formulario com foco e estados',
                                'duration' => '16:20',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'css',
                                'content' => "Boas praticas pedem foco visivel e estados claros em formularios.\n\nRequisitos:\n1. Estilizar borda e padding dos inputs.\n2. Criar estilo :focus para campos.\n3. Estilizar botao principal com hover.",
                                'html_code' => "<form class=\"contact-form\">\n  <label for=\"nome\">Nome</label>\n  <input id=\"nome\" />\n  <label for=\"email\">Email</label>\n  <input id=\"email\" type=\"email\" />\n  <button type=\"submit\">Enviar</button>\n</form>",
                                'css_code' => ".contact-form {\n  max-width: 420px;\n}\n",
                                'js_code' => '',
                            ],
                            [
                                'title' => 'Checkpoint: formularios',
                                'duration' => '07:20',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Confirma os fundamentos de formularios web.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'form-q1',
                                        'question' => 'Qual elemento agrupa campos de entrada?',
                                        'options' => ['<form>', '<table>', '<section>', '<link>'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'form-q2',
                                        'question' => 'Qual input e adequado para email?',
                                        'options' => ['type=\"email\"', 'type=\"mailbox\"', 'type=\"contact\"', 'type=\"string\"'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'form-q3',
                                        'question' => 'Qual atributo conecta label ao input?',
                                        'options' => ['for', 'class', 'name', 'target'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'form-q4',
                                        'question' => 'Qual pseudo-classe estiliza campo selecionado no teclado?',
                                        'options' => [':focus', ':visited', ':after', ':checked-only'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'form-q5',
                                        'question' => 'Para enviar formulario, usa-se normalmente...',
                                        'options' => ['button type=\"submit\"', 'div class=\"submit\"', 'span submit', 'p submit'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Projeto final: pagina completa HTML + CSS',
                        'lessons' => [
                            [
                                'title' => 'Desafio final da apostila',
                                'duration' => '28:00',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "Agora integra todos os capitulos da apostila num unico projeto.\n\nRequisitos:\n1. Criar cabecalho com menu de links.\n2. Incluir secao com imagem e texto formatado.\n3. Incluir tabela de conteudos e formulario de contacto.\n4. Aplicar box-model e layout responsivo basico com CSS.",
                                'html_code' => "<header>\n  <h1>Projeto Final HTML + CSS</h1>\n</header>\n<main>\n  <section>\n    <h2>Sobre o curso</h2>\n    <p>Completa esta pagina com as secoes exigidas.</p>\n  </section>\n</main>",
                                'css_code' => "body {\n  font-family: Arial, sans-serif;\n  margin: 0;\n  padding: 20px;\n}\n",
                                'js_code' => "console.log('Projeto final iniciado');",
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'JavaScript para Interatividade',
                'subtitle' => 'Domina logica, DOM e eventos para criar interfaces dinamicas.',
                'instructor' => 'Afonso Cossa',
                'rating' => 4.7,
                'review_count' => 9150,
                'student_count' => 24400,
                'price' => 2290.00,
                'original_price' => 8700.00,
                'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=400&fit=crop',
                'category' => 'JavaScript',
                'level' => 'Iniciante',
                'total_hours' => 20,
                'description' => 'Da sintaxe basica ate pequenas features reais: este curso ensina JavaScript com foco em resultado no browser.',
                'sections' => [
                    [
                        'title' => 'Fundamentos de JavaScript',
                        'lessons' => [
                            [
                                'title' => 'Variaveis, tipos e operadores',
                                'duration' => '09:30',
                                'is_free' => true,
                                'type' => 'text',
                                'content' => "Aprende como declarar dados e decidir comportamentos com operadores.\n\nObjetivos:\n- Diferenciar let, const e var.\n- Usar comparacoes e operadores logicos.\n- Preparar base para condicoes e funcoes.",
                            ],
                            [
                                'title' => 'Atualizar Mensagens no DOM',
                                'duration' => '12:15',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'javascript',
                                'content' => "Cria uma mensagem dinamica de boas-vindas.\n\nRequisitos:\n1. Ler o nome de estudante numa variavel.\n2. Mostrar texto no elemento #greeting.\n3. Mudar cor da mensagem quando clicar no botao.",
                                'examples' => [
                                    [
                                        'label' => 'Atualizar conteudo no DOM',
                                        'language' => 'js',
                                        'code' => "const greeting = document.getElementById('greeting');\ngreeting.textContent = 'Bem-vinda, Sofia!';",
                                    ],
                                ],
                                'hint' => 'Atualiza o texto com textContent e usa style.color ou classList no clique do botao.',
                                'html_code' => "<h2 id=\"greeting\">Bem-vindo</h2>\n<button id=\"theme\">Mudar destaque</button>",
                                'css_code' => "body { font-family: system-ui, -apple-system, sans-serif; }\n#greeting { color: #0f172a; }\nbutton { margin-top: 12px; border: 1px solid #111827; background: #111827; color: #fff; border-radius: 8px; padding: 8px 12px; }",
                                'js_code' => "const greeting = document.getElementById('greeting');\nconst button = document.getElementById('theme');\nif (button) {\n  button.addEventListener('click', () => {\n    console.log('Implementa a logica de interacao aqui.');\n  });\n}",
                                'validation_rules' => [
                                    ['kind' => 'selector_exists', 'value' => '#greeting'],
                                    ['kind' => 'selector_exists', 'value' => '#theme'],
                                    ['kind' => 'js_includes', 'value' => 'textContent'],
                                    ['kind' => 'js_includes', 'value' => 'addEventListener'],
                                ],
                            ],
                            [
                                'title' => 'Checkpoint de Logica JavaScript',
                                'duration' => '07:50',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Confirma conhecimentos de variaveis, condicoes e funcoes basicas.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'js-core-q1',
                                        'question' => 'Qual afirmacao sobre const e correta?',
                                        'options' => ['Nao pode ser reatribuida apos declaracao', 'Permite redeclarar no mesmo bloco', 'So funciona em funcoes assincronas', 'E igual a var em tudo'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'js-core-q2',
                                        'question' => 'O operador === compara...',
                                        'options' => ['valor e tipo', 'apenas valor', 'apenas tipo', 'comprimento da string'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'js-core-q3',
                                        'question' => 'Qual metodo devolve novo array transformado?',
                                        'options' => ['map', 'forEach', 'push', 'shift'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'js-core-q4',
                                        'question' => 'Qual valor e considerado falsy em JavaScript?',
                                        'options' => ['0', '[]', '{}', '"ok"'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'js-core-q5',
                                        'question' => 'Template literals usam qual delimitador?',
                                        'options' => ['crase (`)', 'aspas simples', 'aspas duplas', 'parenteses'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'DOM e Eventos',
                        'lessons' => [
                            [
                                'title' => 'Filtros com Botoes',
                                'duration' => '14:10',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'javascript',
                                'content' => "Implementa filtro por categoria.\n\nRequisitos:\n1. Escutar clique dos botoes de filtro.\n2. Atualizar texto de estado em #status.\n3. Marcar botao ativo com classe .active.",
                                'examples' => [
                                    [
                                        'label' => 'Atualizar estado do filtro',
                                        'language' => 'js',
                                        'code' => "const filter = button.dataset.filter;\nstatus.textContent = `Filtro atual: \${filter}`;",
                                    ],
                                ],
                                'hint' => 'Remove .active de todos os botoes antes de aplicar no botao clicado.',
                                'html_code' => "<div class=\"filters\">\n  <button data-filter=\"all\">Todos</button>\n  <button data-filter=\"frontend\">Frontend</button>\n  <button data-filter=\"backend\">Backend</button>\n</div>\n<p id=\"status\">Filtro atual: all</p>",
                                'css_code' => ".filters { display: flex; gap: 10px; }\nbutton { border: 1px solid #111827; background: #fff; color: #111827; border-radius: 8px; padding: 8px 12px; }\nbutton.active { background: #111827; color: #fff; }\n#status { margin-top: 14px; font-family: system-ui, sans-serif; }",
                                'js_code' => "const status = document.getElementById('status');\nconst buttons = document.querySelectorAll('.filters button');\nbuttons.forEach((button) => {\n  button.addEventListener('click', () => {\n    console.log('Completa a logica de filtro');\n  });\n});",
                                'validation_rules' => [
                                    ['kind' => 'selector_exists', 'value' => '.filters'],
                                    ['kind' => 'selector_exists', 'value' => '#status'],
                                    ['kind' => 'js_includes', 'value' => 'dataset'],
                                    ['kind' => 'js_includes', 'value' => 'classList'],
                                    ['kind' => 'js_includes', 'value' => 'textContent'],
                                ],
                            ],
                            [
                                'title' => 'Validacao de Formulario Basico',
                                'duration' => '16:20',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'javascript',
                                'content' => "Valida email e contacto antes de submeter.\n\nRequisitos:\n1. Bloquear submissao quando campos estiverem vazios.\n2. Mostrar mensagem de erro em #error.\n3. Mostrar mensagem de sucesso em #success.",
                                'examples' => [
                                    [
                                        'label' => 'Validacao minima',
                                        'language' => 'js',
                                        'code' => "if (!emailValue || !contactValue) {\n  error.textContent = 'Preenche email e contacto.';\n  success.textContent = '';\n}",
                                    ],
                                ],
                                'hint' => 'Usa trim() nos campos para evitar que espacos em branco contem como valor valido.',
                                'html_code' => "<form id=\"signup-form\">\n  <input id=\"email\" type=\"email\" placeholder=\"email@dominio.com\" />\n  <input id=\"contact\" type=\"tel\" placeholder=\"84xxxxxxx\" />\n  <button type=\"submit\">Enviar</button>\n</form>\n<p id=\"error\"></p>\n<p id=\"success\"></p>",
                                'css_code' => "form { display: grid; gap: 10px; max-width: 360px; }\ninput { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; }\nbutton { border: 1px solid #111827; background: #111827; color: #fff; border-radius: 8px; padding: 8px 12px; }\n#error { color: #b91c1c; }\n#success { color: #166534; }",
                                'js_code' => "const form = document.getElementById('signup-form');\nif (form) {\n  form.addEventListener('submit', (event) => {\n    event.preventDefault();\n    console.log('Implementa validacao aqui.');\n  });\n}",
                                'validation_rules' => [
                                    ['kind' => 'selector_exists', 'value' => '#signup-form'],
                                    ['kind' => 'selector_exists', 'value' => '#error'],
                                    ['kind' => 'selector_exists', 'value' => '#success'],
                                    ['kind' => 'js_includes', 'value' => 'preventDefault'],
                                    ['kind' => 'js_includes', 'value' => 'textContent'],
                                    ['kind' => 'js_includes', 'value' => 'trim'],
                                ],
                            ],
                            [
                                'title' => 'Checkpoint de DOM e Eventos',
                                'duration' => '07:30',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Questionario de eventos, seletores e manipulacao de formularios.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'dom-q1',
                                        'question' => 'querySelector retorna...',
                                        'options' => ['o primeiro elemento correspondente', 'todos os elementos em array', 'apenas IDs', 'sempre null'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'dom-q2',
                                        'question' => 'event.preventDefault() e usado para...',
                                        'options' => ['impedir comportamento padrao do evento', 'criar novo evento', 'apagar elemento', 'recarregar pagina'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'dom-q3',
                                        'question' => 'Para ler texto digitado num input usamos...',
                                        'options' => ['input.value', 'input.text', 'input.content', 'input.innerHTML'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'dom-q4',
                                        'question' => 'addEventListener aceita quais argumentos principais?',
                                        'options' => ['tipo de evento e callback', 'apenas callback', 'id do elemento e selector', 'nome da classe e delay'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'dom-q5',
                                        'question' => 'dataset em JavaScript permite...',
                                        'options' => ['ler atributos data-*', 'guardar ficheiros', 'ordenar arrays', 'converter JSON automaticamente'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Mini Projeto',
                        'lessons' => [
                            [
                                'title' => 'To-do List com Persistencia no Browser',
                                'duration' => '24:00',
                                'is_free' => false,
                                'type' => 'project',
                                'language' => 'javascript',
                                'content' => "Projeto final do curso.\n\nRequisitos:\n1. Adicionar tarefas a lista.\n2. Marcar tarefa como concluida.\n3. Guardar tarefas no localStorage.",
                                'examples' => [
                                    [
                                        'label' => 'Persistir tarefas',
                                        'language' => 'js',
                                        'code' => "localStorage.setItem('tasks', JSON.stringify(tasks));\nconst saved = JSON.parse(localStorage.getItem('tasks') || '[]');",
                                    ],
                                ],
                                'hint' => 'Separa em 3 funcoes: adicionar, renderizar e guardar/carregar no localStorage.',
                                'html_code' => "<h2>Minhas tarefas</h2>\n<input id=\"task-input\" placeholder=\"Nova tarefa\" />\n<button id=\"add-task\">Adicionar</button>\n<ul id=\"task-list\"></ul>",
                                'css_code' => "body { font-family: system-ui, sans-serif; padding: 20px; }\ninput, button { padding: 8px; }\nul { margin-top: 12px; }\nli { margin-bottom: 8px; }",
                                'js_code' => "const list = document.getElementById('task-list');\ndocument.getElementById('add-task')?.addEventListener('click', () => {\n  console.log('Completa a to-do list');\n});",
                                'validation_rules' => [
                                    ['kind' => 'selector_exists', 'value' => '#task-input'],
                                    ['kind' => 'selector_exists', 'value' => '#add-task'],
                                    ['kind' => 'selector_exists', 'value' => '#task-list'],
                                    ['kind' => 'js_includes', 'value' => 'localStorage'],
                                    ['kind' => 'js_includes', 'value' => 'createElement'],
                                    ['kind' => 'js_includes', 'value' => 'addEventListener'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Design de Interface e Acessibilidade',
                'subtitle' => 'Cria experiencias claras, acessiveis e orientadas a conversao.',
                'instructor' => 'Marta Simango',
                'rating' => 4.6,
                'review_count' => 6380,
                'student_count' => 17950,
                'price' => 1790.00,
                'original_price' => 6900.00,
                'image' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=600&h=400&fit=crop',
                'category' => 'UX/UI',
                'level' => 'Iniciante',
                'total_hours' => 14,
                'description' => 'Aprende a desenhar interfaces usaveis: tipografia, contraste, feedback visual e componentes acessiveis.',
                'sections' => [
                    [
                        'title' => 'Fundamentos de UX',
                        'lessons' => [
                            [
                                'title' => 'Hierarquia visual e leitura',
                                'duration' => '08:40',
                                'is_free' => true,
                                'type' => 'text',
                                'content' => "Uma interface boa orienta o olhar do utilizador.\n\nNesta licao vais aplicar:\n- Escala de titulos e subtitulos.\n- Espacamento consistente.\n- Contraste suficiente entre texto e fundo.",
                            ],
                            [
                                'title' => 'Formulario Acessivel de Registo',
                                'duration' => '13:30',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'html',
                                'content' => "Melhora acessibilidade deste formulario.\n\nRequisitos:\n1. Associar labels a todos os inputs.\n2. Exibir mensagens de erro claras.\n3. Garantir foco visivel no teclado.",
                                'examples' => [
                                    [
                                        'label' => 'Label associado corretamente',
                                        'language' => 'html',
                                        'code' => "<label for=\"name\">Nome completo</label>\n<input id=\"name\" />",
                                    ],
                                ],
                                'hint' => 'Evita depender apenas de placeholder; o label deve existir para cada campo.',
                                'html_code' => "<form class=\"register\">\n  <input id=\"name\" placeholder=\"Nome completo\" />\n  <input id=\"email\" type=\"email\" placeholder=\"email@dominio.com\" />\n  <button type=\"submit\">Criar conta</button>\n</form>\n<p id=\"help\"></p>",
                                'css_code' => ".register {\n  display: grid;\n  gap: 10px;\n  max-width: 380px;\n}\n\n.register input {\n  border: 1px solid #cbd5e1;\n  border-radius: 8px;\n  padding: 10px;\n}\n\n.register button {\n  border: 1px solid #111827;\n  border-radius: 8px;\n  background: #111827;\n  color: #fff;\n  padding: 10px 14px;\n}",
                                'js_code' => "document.querySelector('.register')?.addEventListener('submit', (event) => {\n  event.preventDefault();\n  console.log('Valida os campos e mostra feedback acessivel.');\n});",
                                'validation_rules' => [
                                    ['kind' => 'selector_exists', 'value' => 'form'],
                                    ['kind' => 'selector_exists', 'value' => '#name'],
                                    ['kind' => 'selector_exists', 'value' => '#email'],
                                    ['kind' => 'html_includes', 'value' => '<label'],
                                    ['kind' => 'html_includes', 'value' => 'for=\"name\"'],
                                    ['kind' => 'html_includes', 'value' => 'for=\"email\"'],
                                ],
                            ],
                            [
                                'title' => 'Checkpoint de Acessibilidade',
                                'duration' => '06:40',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Avalia conceitos de contraste, foco e semantica em formularios.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'a11y-q1',
                                        'question' => 'Qual atributo liga label a input?',
                                        'options' => ['for', 'aria-label-id', 'name', 'target'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'a11y-q2',
                                        'question' => 'Porque o foco visivel e importante?',
                                        'options' => ['Ajuda navegacao por teclado', 'Acelera carregamento', 'Substitui validacao', 'Esconde erros'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'a11y-q3',
                                        'question' => 'Qual pratica melhora legibilidade?',
                                        'options' => ['Contraste adequado entre texto e fundo', 'Texto cinza claro em fundo branco', 'Fonte de 10px para tudo', 'Paragrafos sem espacamento'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'a11y-q4',
                                        'question' => 'Em formularios, mensagens de erro devem...',
                                        'options' => ['explicar o problema e como corrigir', 'apenas mudar cor do campo', 'aparecer so no console', 'ser ocultas para leitores de ecra'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'a11y-q5',
                                        'question' => 'Qual elemento e indicado para acao principal clicavel?',
                                        'options' => ['<button>', '<div>', '<span>', '<p>'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Componentes e Fluxos',
                        'lessons' => [
                            [
                                'title' => 'Estado de botoes e feedback visual',
                                'duration' => '12:20',
                                'is_free' => false,
                                'type' => 'code',
                                'language' => 'css',
                                'content' => "Adiciona estados hover, focus e disabled.\n\nRequisitos:\n1. Botao principal com hover claro.\n2. Estilo :focus-visible para navegacao por teclado.\n3. Variante disabled com contraste adequado.",
                                'examples' => [
                                    [
                                        'label' => 'Estados essenciais',
                                        'language' => 'css',
                                        'code' => ".btn-primary:hover { opacity: .92; }\n.btn-primary:focus-visible { outline: 3px solid #60a5fa; outline-offset: 2px; }\n.btn-primary:disabled { opacity: .55; cursor: not-allowed; }",
                                    ],
                                ],
                                'hint' => 'Aplica :focus-visible para nao poluir cliques de rato e manter acessibilidade no teclado.',
                                'html_code' => "<button class=\"btn-primary\">Guardar alteracoes</button>\n<button class=\"btn-primary\" disabled>A processar</button>",
                                'css_code' => ".btn-primary {\n  border: 1px solid #111827;\n  background: #111827;\n  color: #fff;\n  border-radius: 8px;\n  padding: 10px 14px;\n}",
                                'js_code' => "console.log('Define estados de interacao no CSS.');",
                                'validation_rules' => [
                                    ['kind' => 'selector_exists', 'value' => '.btn-primary'],
                                    ['kind' => 'css_includes', 'value' => ':hover'],
                                    ['kind' => 'css_includes', 'value' => ':focus-visible'],
                                    ['kind' => 'css_includes', 'value' => ':disabled'],
                                ],
                            ],
                            [
                                'title' => 'Checklist de UX para Checkout',
                                'duration' => '06:20',
                                'is_free' => false,
                                'type' => 'quiz',
                                'content' => 'Valida decisoes de UX em formularios de pagamento e compra.',
                                'quiz_pass_percentage' => 80,
                                'quiz_randomize_questions' => true,
                                'quiz_questions' => [
                                    [
                                        'id' => 'ux-q1',
                                        'question' => 'Qual campo deve usar type="email"?',
                                        'options' => ['Endereco de e-mail', 'Nome completo', 'Cidade', 'Codigo postal'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'ux-q2',
                                        'question' => 'Quando mostrar erro de validacao?',
                                        'options' => ['No blur/submissao com mensagem clara', 'Apenas no console', 'Nunca mostrar', 'Somente apos recarregar'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'ux-q3',
                                        'question' => 'Contacto telefonico deve usar qual tipo de input?',
                                        'options' => ['tel', 'number', 'text-area', 'url'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'ux-q4',
                                        'question' => 'Qual acao ajuda reduzir erros em checkout?',
                                        'options' => ['Validacao inline e mensagens objetivas', 'Esconder labels', 'Usar placeholders apenas', 'Bloquear botao sempre'],
                                        'correctOptionIndex' => 0,
                                    ],
                                    [
                                        'id' => 'ux-q5',
                                        'question' => 'Botao de submissao deve ficar desativado quando...',
                                        'options' => ['o formulario estiver invalido ou a processar', 'o utilizador clicar uma vez', 'a pagina abrir', 'houver imagem no formulario'],
                                        'correctOptionIndex' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Projeto Final',
                        'lessons' => [
                            [
                                'title' => 'Checkout Completo com Feedback',
                                'duration' => '18:15',
                                'is_free' => false,
                                'type' => 'project',
                                'language' => 'javascript',
                                'content' => "Entrega final do curso de UX/UI.\n\nRequisitos:\n1. Criar formulario com nome, email, contacto e password.\n2. Validar campos e mostrar erro em vermelho.\n3. Mostrar confirmacao em verde quando dados forem validos.",
                                'examples' => [
                                    [
                                        'label' => 'Fluxo de validacao',
                                        'language' => 'js',
                                        'code' => "if (isValid) {\n  success.textContent = 'Dados validados com sucesso.';\n  error.textContent = '';\n} else {\n  error.textContent = 'Corrige os campos obrigatorios.';\n}",
                                    ],
                                ],
                                'hint' => 'Centraliza validacao numa funcao para manter regras claras e facilitar manutencao.',
                                'html_code' => "<form id=\"checkout\">\n  <label for=\"full-name\">Nome</label>\n  <input id=\"full-name\" />\n  <label for=\"mail\">Email</label>\n  <input id=\"mail\" type=\"email\" />\n  <label for=\"phone\">Contacto</label>\n  <input id=\"phone\" type=\"tel\" />\n  <label for=\"pass\">Password</label>\n  <input id=\"pass\" type=\"password\" />\n  <button type=\"submit\">Finalizar compra</button>\n</form>\n<p id=\"error\"></p>\n<p id=\"success\"></p>",
                                'css_code' => "body { font-family: system-ui, sans-serif; padding: 20px; }\nform { display: grid; gap: 8px; max-width: 420px; }\ninput { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; }\nbutton { margin-top: 8px; border: 1px solid #111827; border-radius: 8px; background: #111827; color: #fff; padding: 10px 12px; }\n#error { color: #b91c1c; margin-top: 10px; }\n#success { color: #166534; margin-top: 6px; }",
                                'js_code' => "document.getElementById('checkout')?.addEventListener('submit', (event) => {\n  event.preventDefault();\n  console.log('Implementa a validacao final do checkout.');\n});",
                                'validation_rules' => [
                                    ['kind' => 'selector_exists', 'value' => '#checkout'],
                                    ['kind' => 'selector_exists', 'value' => '#error'],
                                    ['kind' => 'selector_exists', 'value' => '#success'],
                                    ['kind' => 'js_includes', 'value' => 'preventDefault'],
                                    ['kind' => 'js_includes', 'value' => 'textContent'],
                                    ['kind' => 'js_includes', 'value' => 'trim'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'UI com CSS: do design system ao dashboard',
                'subtitle' => 'Percurso completo para criar interfaces profissionais com CSS: tokens, componentes, responsividade e projeto final.',
                'instructor' => 'Celina Macamo',
                'rating' => 4.8,
                'review_count' => 5210,
                'student_count' => 12840,
                'price' => 1890.00,
                'original_price' => 7100.00,
                'image' => 'https://images.unsplash.com/photo-1545239351-1141bd82e8a6?w=600&h=400&fit=crop',
                'category' => 'UX/UI',
                'level' => 'Iniciante',
                'total_hours' => 42,
                'description' => 'Formacao completa de UI com CSS: design tokens, tipografia, componentes, estados, grids, acessibilidade e projeto final com dashboard.',
                'sections' => $this->buildUiCssMasterSections(),
            ],
        ];

        // Refresh first course with fully structured content for the new lesson experience:
        // Teoria + Exemplos + Instrucoes + Dica, quizzes at 80%, randomization enabled.
        $courses[0]['subtitle'] = 'Do zero ao layout responsivo com teoria orientada, exemplos claros e tarefas praticas.';
        $courses[0]['description'] = 'Percurso completo de fundamentos de HTML e CSS com explicacao guiada, exemplos de codigo, validacao por tarefa e checkpoints por modulo.';
        $courses[0]['total_hours'] = 36;
        $courses[0]['sections'] = $this->buildFundamentosHtmlCssSections();

        foreach ($courses as $courseData) {
            $sections = $courseData['sections'];
            unset($courseData['sections']);

            $totalLessons = 0;
            foreach ($sections as $sectionData) {
                $totalLessons += count($sectionData['lessons']);
            }

            $courseData['total_lessons'] = $totalLessons;

            $course = Course::query()->create($courseData);

            foreach ($sections as $sectionIndex => $sectionData) {
                $lessons = $sectionData['lessons'];

                $section = $course->sections()->create([
                    'title' => $sectionData['title'],
                    'sort_order' => $sectionIndex + 1,
                ]);

                foreach ($lessons as $lessonIndex => $lessonData) {
                    $lessonType = (string) ($lessonData['type'] ?? 'code');
                    $content = $this->formatLessonContent($lessonData, $lessonType);
                    $isCodePracticeLesson = in_array($lessonType, ['code', 'project'], true);
                    $htmlCode = $isCodePracticeLesson ? (string) ($lessonData['html_code'] ?? '') : '';
                    $cssCode = $isCodePracticeLesson ? (string) ($lessonData['css_code'] ?? '') : '';
                    $jsCode = $isCodePracticeLesson ? (string) ($lessonData['js_code'] ?? '') : '';
                    $workspaceFiles = $isCodePracticeLesson
                        ? $this->buildWorkspaceFiles($htmlCode, $cssCode, $jsCode)
                        : null;
                    $validationRules = $isCodePracticeLesson
                        ? $this->resolveValidationRules($lessonData, $htmlCode, $cssCode, $jsCode)
                        : null;

                    $section->lessons()->create([
                        'title' => $lessonData['title'],
                        'duration' => $lessonData['duration'],
                        'is_free' => $lessonData['is_free'],
                        'video_url' => $lessonData['video_url'] ?? null,
                        'text_media_type' => $lessonData['text_media_type'] ?? null,
                        'text_media_image_url' => $lessonData['text_media_image_url'] ?? null,
                        'type' => $lessonType,
                        'language' => $lessonData['language'] ?? null,
                        'content' => $content,
                        'starter_code' => $isCodePracticeLesson ? ($lessonData['starter_code'] ?? null) : null,
                        'html_code' => $isCodePracticeLesson ? ($lessonData['html_code'] ?? null) : null,
                        'css_code' => $isCodePracticeLesson ? ($lessonData['css_code'] ?? null) : null,
                        'js_code' => $isCodePracticeLesson ? ($lessonData['js_code'] ?? null) : null,
                        'workspace_files' => $workspaceFiles,
                        'entry_html_file_id' => $isCodePracticeLesson ? 'index-html' : null,
                        'validation_rules' => $validationRules,
                        'quiz_questions' => $lessonData['quiz_questions'] ?? null,
                        'quiz_pass_percentage' => $lessonData['quiz_pass_percentage'] ?? null,
                        'quiz_randomize_questions' => $lessonData['quiz_randomize_questions'] ?? null,
                        'sort_order' => $lessonIndex + 1,
                    ]);
                }
            }
        }
    }

    private function buildFundamentosHtmlCssSections(): array
    {
        return [
            [
                'title' => '1. Fundamentos da web e estrutura HTML',
                'lessons' => [
                    [
                        'title' => 'Como o browser le HTML e CSS',
                        'duration' => '09:40',
                        'is_free' => true,
                        'type' => 'text',
                        'content' => <<<'TEXT'
Teoria
HTML organiza o conteudo da pagina. CSS define aparencia, espacos e hierarquia visual.
Quando o browser abre um ficheiro HTML, ele cria uma arvore de elementos e aplica as regras CSS em cascata.

Exemplos
```html
<h1>Bem-vindo</h1>
<p>Esta frase e estrutura (HTML).</p>
```
```css
h1 { color: #111; }
p { line-height: 1.6; }
```

Dica: pensa em HTML como "o que existe" e CSS como "como aparece".
TEXT,
                    ],
                    [
                        'title' => 'Criar documento HTML completo',
                        'duration' => '14:20',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'html',
                        'content' => <<<'TEXT'
Uma pagina precisa de uma estrutura base para ser valida e previsivel em qualquer browser.
Requisitos:
1. Adiciona doctype, html, head e body.
2. Define um title com "Fundamentos HTML".
3. Dentro do body cria um h1 e dois paragrafos.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Estrutura minima',
                                'language' => 'html',
                                'code' => "<!doctype html>\n<html lang=\"pt\">\n  <head>\n    <meta charset=\"UTF-8\" />\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />\n    <title>Fundamentos HTML</title>\n  </head>\n  <body>\n    <h1>Ola mundo</h1>\n    <p>Primeiro paragrafo.</p>\n  </body>\n</html>",
                            ],
                        ],
                        'hint' => 'Comeca pela estrutura externa e so depois adiciona o conteudo dentro de body.',
                        'html_code' => "<h1>Curso de HTML + CSS</h1>\n<p>Completa este ficheiro com a estrutura do documento.</p>",
                        'css_code' => "body {\n  font-family: Arial, sans-serif;\n  line-height: 1.5;\n  margin: 20px;\n}",
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Checkpoint: fundamentos iniciais',
                        'duration' => '07:10',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Questionario de revisao sobre estrutura de documento e papel de HTML/CSS.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'fnd-intro-q1',
                                'question' => 'Qual linguagem define a estrutura da pagina?',
                                'options' => ['HTML', 'CSS', 'PNG', 'SQL'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-intro-q2',
                                'question' => 'A tag title fica dentro de qual elemento?',
                                'options' => ['head', 'body', 'footer', 'main'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-intro-q3',
                                'question' => 'O doctype serve para...',
                                'options' => ['indicar o tipo de documento ao browser', 'aplicar CSS', 'executar JavaScript', 'criar links'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-intro-q4',
                                'question' => 'CSS e usado para...',
                                'options' => ['estilo e layout', 'estrutura semantica', 'consultas de base de dados', 'compressao de imagens'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-intro-q5',
                                'question' => 'Qual tag representa o corpo visivel da pagina?',
                                'options' => ['body', 'head', 'meta', 'title'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '2. Texto, listas e links',
                'lessons' => [
                    [
                        'title' => 'Titulos, paragrafos e enfase semantica',
                        'duration' => '15:00',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'html',
                        'content' => <<<'TEXT'
Marcar texto corretamente melhora acessibilidade e SEO.
Requisitos:
1. Cria um h1, um h2 e tres paragrafos.
2. Usa strong para destaque importante e em para enfase.
3. Usa br em um ponto do texto para quebra de linha controlada.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Destaque e enfase',
                                'language' => 'html',
                                'code' => "<p>Aprender <strong>HTML semantico</strong> melhora a estrutura.</p>\n<p>Este termo esta em <em>enfase</em>.</p>",
                            ],
                        ],
                        'hint' => 'Usa h1 apenas uma vez por pagina e desce para h2/h3 conforme a hierarquia.',
                        'html_code' => "<article>\n  <h1></h1>\n  <h2></h2>\n  <p></p>\n</article>",
                        'css_code' => 'body { font-family: Arial, sans-serif; margin: 20px; }',
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Listas e links de navegacao',
                        'duration' => '14:40',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'html',
                        'content' => <<<'TEXT'
Listas e links sao a base de menus e documentos navegaveis.
Requisitos:
1. Cria uma lista ordenada com 4 passos.
2. Cria uma lista nao ordenada com 4 recursos.
3. Adiciona um link externo com target _blank e um link interno para #contato.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Link externo seguro',
                                'language' => 'html',
                                'code' => '<a href="https://developer.mozilla.org" target="_blank" rel="noreferrer">MDN</a>',
                            ],
                        ],
                        'hint' => 'Sempre que usares ancora interna, garante que o id do destino existe.',
                        'html_code' => "<nav>\n  <h2>Guia da Aula</h2>\n</nav>\n<section id=\"contato\">\n  <h3>Contacto</h3>\n</section>",
                        'css_code' => "body { font-family: Arial, sans-serif; margin: 20px; }\nnav { margin-bottom: 16px; }",
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Checkpoint: texto e links',
                        'duration' => '07:30',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Confirma dominio de titulos, listas e links.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'fnd-text-q1',
                                'question' => 'Qual tag representa um paragrafo?',
                                'options' => ['<p>', '<li>', '<h6>', '<a>'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-text-q2',
                                'question' => 'Qual tag cria lista ordenada?',
                                'options' => ['<ol>', '<ul>', '<dl>', '<li>'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-text-q3',
                                'question' => 'Para abrir link em nova aba usa-se...',
                                'options' => ['target="_blank"', 'target="_self"', 'open="new"', 'href="tab"'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-text-q4',
                                'question' => 'Qual elemento cria destaque de importancia semantica?',
                                'options' => ['<strong>', '<b>', '<span>', '<u>'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-text-q5',
                                'question' => 'Link interno para mesma pagina usa...',
                                'options' => ['href="#id"', 'href="page.html"', 'src="#id"', 'target="#id"'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '3. Midia e tabelas',
                'lessons' => [
                    [
                        'title' => 'Imagens com alt, figure e figcaption',
                        'duration' => '15:20',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'html',
                        'content' => <<<'TEXT'
Conteudo visual deve manter contexto mesmo sem imagem carregada.
Requisitos:
1. Insere uma imagem com src valido e alt descritivo.
2. Envolve imagem em figure e adiciona figcaption.
3. Define largura da imagem para manter proporcao no layout.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Imagem semantica',
                                'language' => 'html',
                                'code' => "<figure>\n  <img src=\"https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=800\" alt=\"Pessoa a programar\" width=\"420\" />\n  <figcaption>Ambiente de estudo de desenvolvimento web.</figcaption>\n</figure>",
                            ],
                        ],
                        'hint' => 'Evita alt generico como "imagem"; descreve o significado para quem nao ve a foto.',
                        'html_code' => "<section>\n  <h2>Galeria</h2>\n</section>",
                        'css_code' => "body { font-family: Arial, sans-serif; margin: 20px; }\nimg { border-radius: 8px; }",
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Tabelas com thead, tbody e alinhamento',
                        'duration' => '16:10',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'html',
                        'content' => <<<'TEXT'
Tabelas devem separar cabecalho e corpo para leitura e manutencao.
Requisitos:
1. Cria table com thead e tbody.
2. Define 3 colunas no cabecalho.
3. Preenche 4 linhas de dados.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Estrutura de tabela',
                                'language' => 'html',
                                'code' => "<table>\n  <thead>\n    <tr><th>Modulo</th><th>Aulas</th><th>Status</th></tr>\n  </thead>\n  <tbody>\n    <tr><td>HTML</td><td>8</td><td>Concluido</td></tr>\n  </tbody>\n</table>",
                            ],
                        ],
                        'hint' => 'Mantem o mesmo numero de celulas em todas as linhas para evitar quebra de estrutura.',
                        'html_code' => "<h2>Plano semanal</h2>\n<table>\n</table>",
                        'css_code' => "table { border-collapse: collapse; width: 100%; }\nth, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }",
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Checkpoint: midia e tabelas',
                        'duration' => '07:00',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Questionario de revisao sobre imagens e tabelas.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'fnd-media-q1',
                                'question' => 'Qual atributo define texto alternativo em imagem?',
                                'options' => ['alt', 'title', 'legend', 'caption'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-media-q2',
                                'question' => 'Qual elemento agrupa imagem e legenda?',
                                'options' => ['figure', 'section', 'header', 'aside'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-media-q3',
                                'question' => 'Cabecalho de tabela deve ficar em...',
                                'options' => ['thead', 'tbody', 'tfoot', 'th-only'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-media-q4',
                                'question' => 'Celula de cabecalho usa qual tag?',
                                'options' => ['<th>', '<td>', '<tr>', '<thead>'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-media-q5',
                                'question' => 'Para bordas unicas na tabela usa-se...',
                                'options' => ['border-collapse: collapse', 'table-layout: fixed', 'display: block', 'position: table'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '4. CSS base: seletores, tipografia e box model',
                'lessons' => [
                    [
                        'title' => 'Seletores e cascata no CSS',
                        'duration' => '10:20',
                        'is_free' => false,
                        'type' => 'text',
                        'content' => <<<'TEXT'
Teoria
Seletores definem onde a regra sera aplicada. A cascata decide qual regra vence.
A ordem importa: regras mais especificas ou mais recentes podem sobrescrever regras anteriores.

Exemplos
```css
p { color: #111; }
.destaque { color: #2563eb; }
```

Dica: prefere classes para componentes reutilizaveis e evita depender de seletores muito longos.
TEXT,
                    ],
                    [
                        'title' => 'Tipografia, cores e espacamento consistente',
                        'duration' => '16:30',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => <<<'TEXT'
Uma interface clara comeca com escala tipografica e espacamentos previsiveis.
Requisitos:
1. Define font-family, font-size base e line-height no body.
2. Aplica cor principal no h1 e cor secundaria nos paragrafos.
3. Cria uma classe .section com padding e margin consistentes.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Escala simples de tipografia',
                                'language' => 'css',
                                'code' => "body { font-family: 'Segoe UI', sans-serif; font-size: 16px; line-height: 1.6; }\nh1 { font-size: 2rem; }\np { color: #374151; }",
                            ],
                        ],
                        'hint' => 'Usa unidades relativas (rem) para facilitar adaptacao em diferentes ecras.',
                        'html_code' => "<main class=\"section\">\n  <h1>Guia de Estilo</h1>\n  <p>Define padrao visual para esta pagina.</p>\n</main>",
                        'css_code' => ".section {\n}\n",
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Box model na pratica com cartao informativo',
                        'duration' => '15:40',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => <<<'TEXT'
Box model controla como cada bloco ocupa espaco.
Requisitos:
1. Em .card define border, border-radius e padding.
2. Aplica margin-top para separar do cabecalho.
3. Adiciona box-shadow suave para destacar o cartao.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Cartao com box model',
                                'language' => 'css',
                                'code' => ".card {\n  border: 1px solid #d1d5db;\n  border-radius: 12px;\n  padding: 16px;\n  margin-top: 20px;\n}",
                            ],
                        ],
                        'hint' => 'Se o elemento ficar maior do que esperado, revisa a soma de padding + border + largura.',
                        'html_code' => "<article class=\"card\">\n  <h2>Resumo da aula</h2>\n  <p>Treinar box model deixa o layout previsivel.</p>\n</article>",
                        'css_code' => ".card {\n  background: #f8fafc;\n}\n",
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Checkpoint: CSS base',
                        'duration' => '07:10',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Revisa seletores, cascata e box model.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'fnd-css-q1',
                                'question' => 'Qual seletor representa classe?',
                                'options' => ['.classe', '#classe', 'classe()', '@classe'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-css-q2',
                                'question' => 'Padding representa...',
                                'options' => ['espaco interno', 'espaco externo', 'borda', 'altura'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-css-q3',
                                'question' => 'Margin representa...',
                                'options' => ['espaco externo', 'espaco interno', 'sombra', 'largura'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-css-q4',
                                'question' => 'A cascata considera...',
                                'options' => ['especificidade e ordem das regras', 'apenas ordem alfabetica', 'apenas cor', 'apenas tamanho da fonte'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-css-q5',
                                'question' => 'Qual propriedade arredonda cantos?',
                                'options' => ['border-radius', 'outline-radius', 'corner', 'radius-border'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '5. Layout responsivo com Flexbox e Grid',
                'lessons' => [
                    [
                        'title' => 'Duas colunas com flexbox',
                        'duration' => '17:00',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => <<<'TEXT'
Flexbox facilita distribuicao de espaco entre blocos.
Requisitos:
1. Define .layout com display flex e gap entre colunas.
2. Sidebar deve ocupar menos espaco que main content.
3. Em largura pequena, muda para coluna unica.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Layout responsivo com flex',
                                'language' => 'css',
                                'code' => ".layout { display: flex; gap: 16px; }\n.sidebar { flex: 1; }\n.content { flex: 2; }\n@media (max-width: 768px) { .layout { flex-direction: column; } }",
                            ],
                        ],
                        'hint' => 'Testa o layout em largura pequena cedo para evitar retrabalho no final.',
                        'html_code' => "<div class=\"layout\">\n  <aside class=\"sidebar\">Navegacao</aside>\n  <main class=\"content\">Conteudo principal</main>\n</div>",
                        'css_code' => ".layout {\n}\n.sidebar { background: #e5e7eb; padding: 12px; }\n.content { background: #f3f4f6; padding: 12px; }",
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Galeria de cards com CSS Grid',
                        'duration' => '17:20',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => <<<'TEXT'
Grid e ideal para estruturas de cards e galerias.
Requisitos:
1. Define .cards com display grid.
2. Usa repeat e minmax para quantidade automatica de colunas.
3. Ajusta espacamento e altura minima dos cards.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Grid de cards',
                                'language' => 'css',
                                'code' => ".cards {\n  display: grid;\n  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));\n  gap: 16px;\n}",
                            ],
                        ],
                        'hint' => 'auto-fit + minmax reduz media queries para cenarios comuns de grid.',
                        'html_code' => "<section class=\"cards\">\n  <article class=\"card\">Card 1</article>\n  <article class=\"card\">Card 2</article>\n  <article class=\"card\">Card 3</article>\n</section>",
                        'css_code' => '.card { border: 1px solid #d1d5db; border-radius: 10px; min-height: 110px; padding: 12px; }',
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Checkpoint: layout responsivo',
                        'duration' => '07:20',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Valida fundamentos de flexbox, grid e media query.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'fnd-layout-q1',
                                'question' => 'Qual propriedade ativa flexbox?',
                                'options' => ['display: flex', 'display: grid', 'position: flex', 'layout: flex'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-layout-q2',
                                'question' => 'Qual propriedade cria colunas no grid?',
                                'options' => ['grid-template-columns', 'grid-columns-count', 'column-template', 'layout-columns'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-layout-q3',
                                'question' => 'Media query e usada para...',
                                'options' => ['adaptar layout por largura/dispositivo', 'executar JS', 'comprimir CSS', 'criar tabelas'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-layout-q4',
                                'question' => 'Gap em flex/grid define...',
                                'options' => ['espaco entre itens', 'borda dos itens', 'altura minima', 'alinhamento vertical apenas'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-layout-q5',
                                'question' => 'Qual abordagem melhora responsividade de cards?',
                                'options' => ['repeat(auto-fit, minmax(...))', 'width fixa para tudo', 'position absolute em todos', 'zoom do browser'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '6. Formularios acessiveis e validacao visual',
                'lessons' => [
                    [
                        'title' => 'Formulario com labels e campos corretos',
                        'duration' => '16:00',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'html',
                        'content' => <<<'TEXT'
Formularios bons sao claros, acessiveis e validos desde o HTML.
Requisitos:
1. Cria formulario com nome, email, contacto e password.
2. Associa cada label ao input correto com for/id.
3. Define tipos adequados (email, tel, password) e placeholders uteis.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Campo com label associado',
                                'language' => 'html',
                                'code' => "<label for=\"email\">E-mail</label>\n<input id=\"email\" type=\"email\" placeholder=\"nome@dominio.com\" />",
                            ],
                        ],
                        'hint' => 'Inputs sem label tornam navegacao por leitor de ecra muito mais dificil.',
                        'html_code' => "<form class=\"register-form\">\n</form>",
                        'css_code' => '.register-form { max-width: 460px; display: grid; gap: 10px; }',
                        'js_code' => '',
                    ],
                    [
                        'title' => 'Estados de erro e sucesso no formulario',
                        'duration' => '17:20',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => <<<'TEXT'
Feedback visual ajuda o utilizador a corrigir rapido.
Requisitos:
1. Cria classes .field-error e .field-success para bordas.
2. Estiliza mensagens de erro em vermelho e sucesso em verde.
3. Define estado de foco visivel para navegacao por teclado.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Estado de validacao',
                                'language' => 'css',
                                'code' => ".field-error { border-color: #dc2626; }\n.field-success { border-color: #16a34a; }\n.error-text { color: #b91c1c; }\n.success-text { color: #166534; }",
                            ],
                        ],
                        'hint' => 'Nao dependas so de cor: combina texto explicativo para cada erro.',
                        'html_code' => "<form class=\"checkout-form\">\n  <label for=\"name\">Nome</label>\n  <input id=\"name\" class=\"field-error\" />\n  <p class=\"error-text\">Nome obrigatorio.</p>\n</form>",
                        'css_code' => ".checkout-form { max-width: 460px; display: grid; gap: 8px; }\ninput { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; }",
                        'js_code' => "console.log('Implementa a validacao visual dos campos.');",
                    ],
                    [
                        'title' => 'Checkpoint: formularios e validacao',
                        'duration' => '07:10',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Consolida fundamentos de formularios acessiveis e feedback.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'fnd-form-q1',
                                'question' => 'Qual atributo liga label ao input?',
                                'options' => ['for', 'name', 'value', 'aria-role'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-form-q2',
                                'question' => 'Tipo correto para email e...',
                                'options' => ['type="email"', 'type="mail"', 'type="text-email"', 'type="contact"'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-form-q3',
                                'question' => 'Feedback de erro deve ser...',
                                'options' => ['claro e objetivo', 'apenas uma borda sem texto', 'escondido no console', 'inexistente'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-form-q4',
                                'question' => 'Foco visivel ajuda...',
                                'options' => ['navegacao por teclado', 'carregamento da pagina', 'compressao de CSS', 'SEO de imagens'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'fnd-form-q5',
                                'question' => 'Botao de envio deve ficar desativado quando...',
                                'options' => ['formulario esta invalido ou a processar', 'a pagina carrega', 'o cursor sai do campo', 'o utilizador usa teclado'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Projeto final: landing page HTML + CSS',
                'lessons' => [
                    [
                        'title' => 'Projeto final integrador',
                        'duration' => '30:00',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'html',
                        'content' => <<<'TEXT'
Integra tudo que aprendeste: estrutura, tipografia, layout, midia e formulario.
Requisitos:
1. Monta cabecalho com navegacao e chamada principal.
2. Cria secao de beneficios com cards em grid responsivo.
3. Adiciona tabela simples de plano e formulario de contacto.
4. Garante foco visivel, contraste e espacos consistentes.
TEXT,
                        'examples' => [
                            [
                                'label' => 'Estrutura recomendada',
                                'language' => 'html',
                                'code' => "<header>...</header>\n<main>\n  <section class=\"hero\">...</section>\n  <section class=\"benefits\">...</section>\n  <section class=\"pricing\">...</section>\n  <section class=\"contact\">...</section>\n</main>",
                            ],
                            [
                                'label' => 'Grid de cards',
                                'language' => 'css',
                                'code' => ".benefit-grid {\n  display: grid;\n  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));\n  gap: 16px;\n}",
                            ],
                        ],
                        'hint' => 'Desenvolve por blocos: primeiro estrutura HTML, depois CSS de layout e por fim detalhes visuais.',
                        'html_code' => "<header class=\"site-header\">\n  <h1>Fundamentos de HTML + CSS</h1>\n  <nav>\n    <a href=\"#benefits\">Beneficios</a>\n    <a href=\"#pricing\">Planos</a>\n    <a href=\"#contact\">Contacto</a>\n  </nav>\n</header>\n<main>\n  <section id=\"benefits\">\n    <h2>Beneficios</h2>\n    <p>Completa esta landing page com as secoes da tarefa.</p>\n  </section>\n</main>",
                        'css_code' => "body {\n  font-family: Arial, sans-serif;\n  margin: 0;\n  color: #111827;\n}\n.site-header {\n  border-bottom: 1px solid #e5e7eb;\n  padding: 20px;\n}\n",
                        'js_code' => "console.log('Projeto final iniciado');",
                    ],
                ],
            ],
        ];
    }

    private function buildUiCssMasterSections(): array
    {
        return [
            [
                'title' => '1. Fundamentos visuais e design tokens',
                'lessons' => [
                    [
                        'title' => 'Hierarquia visual para interfaces',
                        'duration' => '09:20',
                        'is_free' => true,
                        'type' => 'text',
                        'text_media_type' => 'image',
                        'text_media_image_url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1400&h=900&fit=crop',
                        'content' => <<<'TEXT'
A hierarquia visual organiza a ordem de leitura da interface. O utilizador deve perceber
em poucos segundos qual e a mensagem principal, qual e o contexto e qual acao deve executar.
Quando titulo, subtitulo e botao competem com o mesmo peso visual, a interface perde clareza.

Em UI com CSS, hierarquia nasce da combinacao entre escala tipografica, contraste de cor,
espacamento e agrupamento de elementos relacionados. Titulos maiores e com maior peso
capturam atencao primeiro, enquanto texto de apoio com contraste mais suave reduz ruido.

Tambem e importante limitar a quantidade de pontos focais por secao. Um bloco com uma unica
acao primaria tende a converter melhor do que um bloco com varios botoes de mesmo destaque.
Isto melhora legibilidade, reduz hesitacao e torna a navegacao mais previsivel.

Na pratica, esta base sera usada nas proximas licoes para construir hero sections, cards e
componentes com prioridade visual consistente em desktop e mobile.
TEXT,
                    ],
                    [
                        'title' => 'Leitura guiada de uma landing page real',
                        'duration' => '08:10',
                        'is_free' => false,
                        'type' => 'text',
                        'text_media_type' => 'youtube',
                        'video_url' => 'https://www.youtube.com/watch?v=UB1O30fR-EE',
                        'content' => <<<'TEXT'
Uma landing page eficaz segue uma narrativa visual: apresenta promessa de valor,
cria contexto, mostra beneficios e conduz para uma chamada para acao clara.
Mesmo quando o design parece simples, existe uma intencao forte na ordem dos blocos.

Ao analisar um exemplo real, repara como cabecalho, hero, prova social, beneficios e
preco se ligam numa sequencia logica. Cada secao responde a uma pergunta do utilizador:
o que e isto, porque importa, como funciona e qual e o proximo passo.

No CSS, esta organizacao depende de composicao, espacamento e contraste. O layout precisa
de manter consistencia entre secoes, garantindo leitura fluida em diferentes larguras
de ecra sem perder foco no conteudo principal.

Esta leitura orientada por estrutura vai servir como referencia para o projeto final,
onde vais transformar estes principios em componentes reutilizaveis e layout responsivo.
TEXT,
                    ],
                    [
                        'title' => 'Base do design system com variaveis CSS',
                        'duration' => '15:30',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Cria uma base de design tokens reutilizavel.\n\nRequisitos:\n1. Definir variaveis em :root para cores, espacamento e radius.\n2. Aplicar os tokens no body, no card e no botao principal.\n3. Remover valores fixos de cor dos componentes principais.",
                        'examples' => [
                            [
                                'label' => 'Token baseline',
                                'language' => 'css',
                                'code' => ":root {\n  --color-bg: #f8fafc;\n  --color-text: #0f172a;\n  --color-primary: #2563eb;\n  --space-md: 16px;\n}",
                            ],
                        ],
                        'hint' => 'Comeca por definir os tokens e so depois substitui o CSS existente por var(...).',
                        'html_code' => "<main class=\"page\">\n  <article class=\"card\">\n    <h1>Design System</h1>\n    <p>Configura tokens e aplica nos componentes.</p>\n    <button class=\"btn-primary\">Comecar</button>\n  </article>\n</main>",
                        'css_code' => ".page { padding: 24px; }\n.card { border-radius: 12px; padding: 16px; }\n.btn-primary { border: 0; border-radius: 8px; padding: 10px 14px; }",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'css_includes', 'value' => ':root'],
                            ['kind' => 'css_includes', 'value' => '--color-primary'],
                            ['kind' => 'css_includes', 'value' => 'var(--color-primary)'],
                            ['kind' => 'selector_exists', 'value' => '.btn-primary'],
                        ],
                    ],
                    [
                        'title' => 'Checkpoint: design tokens',
                        'duration' => '07:00',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Questionario sobre tokens, contraste e consistencia visual.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'ui-tokens-q1',
                                'question' => 'Onde normalmente declaramos tokens globais em CSS?',
                                'options' => [':root', '.container', 'header', '@media'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-tokens-q2',
                                'question' => 'Qual vantagem de var(--token)?',
                                'options' => ['consistencia e manutencao', 'executar JavaScript', 'aumentar peso do HTML', 'eliminar classes'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-tokens-q3',
                                'question' => 'Contraste adequado melhora...',
                                'options' => ['legibilidade', 'latencia de rede', 'cache do browser', 'minificacao'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-tokens-q4',
                                'question' => 'Em UI, espacamento consistente ajuda...',
                                'options' => ['hierarquia e leitura', 'quebra de layout', 'erro de renderizacao', 'reduzir semantica'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-tokens-q5',
                                'question' => 'Qual abordagem e melhor para escalabilidade?',
                                'options' => ['classes reutilizaveis + tokens', 'somente estilo inline', 'somente tags sem classe', 'cores hardcoded'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '2. Tipografia, cor e superficies',
                'lessons' => [
                    [
                        'title' => 'Escala tipografica e ritmo vertical',
                        'duration' => '16:10',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Configura tipografia para leitura profissional.\n\nRequisitos:\n1. Definir escala de font-size com unidades rem.\n2. Ajustar line-height para titulos e paragrafos.\n3. Criar classes utilitarias para titulo, subtitulo e texto secundario.",
                        'examples' => [
                            [
                                'label' => 'Escala basica',
                                'language' => 'css',
                                'code' => "h1 { font-size: 2rem; line-height: 1.2; }\np { font-size: 1rem; line-height: 1.6; }",
                            ],
                        ],
                        'hint' => 'Usa rem para manter proporcionalidade quando o utilizador aumenta o zoom.',
                        'html_code' => "<section>\n  <h1 class=\"page-title\">UI CSS Master</h1>\n  <p class=\"lead\">Define escala e legibilidade.</p>\n  <p class=\"text-muted\">Texto de apoio.</p>\n</section>",
                        'css_code' => "body { font-family: 'Segoe UI', sans-serif; }\n.page-title {}\n.lead {}\n.text-muted {}",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.page-title'],
                            ['kind' => 'css_includes', 'value' => 'font-size'],
                            ['kind' => 'css_includes', 'value' => 'line-height'],
                            ['kind' => 'css_includes', 'value' => 'rem'],
                        ],
                    ],
                    [
                        'title' => 'Cards e superficies com elevacao',
                        'duration' => '15:20',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Trabalha superficies e profundidade visual.\n\nRequisitos:\n1. Estilizar .info-card com fundo, borda e radius.\n2. Adicionar box-shadow suave para elevacao.\n3. Criar estado hover com pequena elevacao visual.",
                        'examples' => [
                            [
                                'label' => 'Card com elevacao',
                                'language' => 'css',
                                'code' => ".info-card {\n  border-radius: 12px;\n  box-shadow: 0 8px 20px rgba(15,23,42,.08);\n}",
                            ],
                        ],
                        'hint' => 'Mantenha a sombra suave para nao parecer interface pesada.',
                        'html_code' => "<article class=\"info-card\">\n  <h2>Relatorio semanal</h2>\n  <p>Resumo de desempenho de estudantes.</p>\n</article>",
                        'css_code' => ".info-card { padding: 16px; border: 1px solid #d1d5db; }\n",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.info-card'],
                            ['kind' => 'css_includes', 'value' => 'box-shadow'],
                            ['kind' => 'css_includes', 'value' => 'border-radius'],
                            ['kind' => 'css_includes', 'value' => ':hover'],
                        ],
                    ],
                    [
                        'title' => 'Checkpoint: tipografia e superficies',
                        'duration' => '07:00',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Revisa escala tipografica, legibilidade e elevacao visual.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'ui-type-q1',
                                'question' => 'Qual unidade e preferida para escala tipografica acessivel?',
                                'options' => ['rem', 'px fixo em tudo', 'vh', '% no body apenas'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-type-q2',
                                'question' => 'Qual propriedade melhora legibilidade de paragrafos?',
                                'options' => ['line-height', 'z-index', 'float', 'position'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-type-q3',
                                'question' => 'Elevacao visual em card e geralmente feita com...',
                                'options' => ['box-shadow', 'clip-path', 'zoom', 'mix-blend-mode'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-type-q4',
                                'question' => 'Qual pratica ajuda consistencia de texto?',
                                'options' => ['definir classes tipograficas reutilizaveis', 'usar tamanho aleatorio por secao', 'usar somente inline style', 'evitar heading'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-type-q5',
                                'question' => 'Hover em card deve...',
                                'options' => ['dar feedback sutil', 'mover 200px para baixo', 'sumir com o card', 'mudar toda a pagina'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '3. Componentes de acao e formularios',
                'lessons' => [
                    [
                        'title' => 'Botoes com estados completos',
                        'duration' => '15:40',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Cria botoes com interacao consistente.\n\nRequisitos:\n1. Estilizar botao primario e secundario.\n2. Definir estados :hover e :focus-visible.\n3. Definir estado disabled com contraste adequado.",
                        'examples' => [
                            [
                                'label' => 'Estados de botao',
                                'language' => 'css',
                                'code' => ".btn-primary:hover { filter: brightness(.95); }\n.btn-primary:focus-visible { outline: 3px solid #93c5fd; }\n.btn-primary:disabled { opacity: .55; }",
                            ],
                        ],
                        'hint' => 'O estado de foco precisa ser visivel sem depender da cor de hover.',
                        'html_code' => "<button class=\"btn-primary\">Guardar</button>\n<button class=\"btn-secondary\">Cancelar</button>\n<button class=\"btn-primary\" disabled>A guardar...</button>",
                        'css_code' => ".btn-primary { border: 0; border-radius: 8px; padding: 10px 14px; }\n.btn-secondary { border: 1px solid #94a3b8; border-radius: 8px; padding: 10px 14px; }",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.btn-primary'],
                            ['kind' => 'selector_exists', 'value' => '.btn-secondary'],
                            ['kind' => 'css_includes', 'value' => ':hover'],
                            ['kind' => 'css_includes', 'value' => ':focus-visible'],
                            ['kind' => 'css_includes', 'value' => ':disabled'],
                        ],
                    ],
                    [
                        'title' => 'Campos de formulario com erro e sucesso',
                        'duration' => '16:20',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Implementa feedback visual de validacao em formulario.\n\nRequisitos:\n1. Definir classes .field-error e .field-success.\n2. Estilizar mensagens .error-text e .success-text.\n3. Aplicar :focus-visible nos inputs.",
                        'examples' => [
                            [
                                'label' => 'Feedback de validacao',
                                'language' => 'css',
                                'code' => ".field-error { border-color: #dc2626; }\n.field-success { border-color: #16a34a; }\n.error-text { color: #b91c1c; }",
                            ],
                        ],
                        'hint' => 'Nao uses apenas cor: combina cor + texto para feedback acessivel.',
                        'html_code' => "<form class=\"signup-form\">\n  <label for=\"email\">Email</label>\n  <input id=\"email\" class=\"field-error\" />\n  <p class=\"error-text\">Email invalido.</p>\n  <input id=\"phone\" class=\"field-success\" />\n  <p class=\"success-text\">Contacto valido.</p>\n</form>",
                        'css_code' => ".signup-form { max-width: 420px; display: grid; gap: 8px; }\ninput { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; }",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.signup-form'],
                            ['kind' => 'css_includes', 'value' => '.field-error'],
                            ['kind' => 'css_includes', 'value' => '.field-success'],
                            ['kind' => 'css_includes', 'value' => '.error-text'],
                            ['kind' => 'css_includes', 'value' => ':focus-visible'],
                        ],
                    ],
                    [
                        'title' => 'Checkpoint: componentes de formulario',
                        'duration' => '07:00',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Valida fundamentos de estados de botao e formulario.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'ui-form-q1',
                                'question' => 'Qual seletor aplica foco para teclado de forma apropriada?',
                                'options' => [':focus-visible', ':hover', ':target', ':checked'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-form-q2',
                                'question' => 'Classe de erro deve indicar...',
                                'options' => ['estado invalido de campo', 'que o campo esta desativado', 'que a pagina terminou', 'que o tema e escuro'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-form-q3',
                                'question' => 'Estado disabled no botao serve para...',
                                'options' => ['bloquear acao indisponivel', 'esconder botao', 'submeter automatico', 'substituir validacao'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-form-q4',
                                'question' => 'Qual pratica melhora clareza de erro?',
                                'options' => ['mensagem textual objetiva', 'apenas trocar cor da borda', 'remover label', 'usar placeholder vazio'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-form-q5',
                                'question' => 'Em UI, consistencia de estados ajuda...',
                                'options' => ['previsibilidade da interacao', 'aumentar complexidade', 'quebrar fluxo', 'reduzir usabilidade'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '4. Navegacao e hero responsivo',
                'lessons' => [
                    [
                        'title' => 'Navbar responsiva com Flexbox',
                        'duration' => '16:40',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Monta cabecalho responsivo para produto digital.\n\nRequisitos:\n1. Organizar logo, menu e CTA com display flex.\n2. Definir espacamento consistente entre itens.\n3. Em mobile, ajustar para layout de coluna com media query.",
                        'examples' => [
                            [
                                'label' => 'Header flex',
                                'language' => 'css',
                                'code' => ".site-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }",
                            ],
                        ],
                        'hint' => 'Comeca desktop-first e testa breakpoints ao reduzir a janela.',
                        'html_code' => "<header class=\"site-header\">\n  <h1 class=\"brand\">UrSkool UI</h1>\n  <nav class=\"menu\">\n    <a href=\"#\">Cursos</a>\n    <a href=\"#\">Planos</a>\n    <a href=\"#\">Contacto</a>\n  </nav>\n  <button class=\"btn-primary\">Inscrever</button>\n</header>",
                        'css_code' => ".menu { display: flex; gap: 10px; }\n.btn-primary { border: 0; border-radius: 8px; padding: 10px 14px; }",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.site-header'],
                            ['kind' => 'selector_exists', 'value' => '.menu'],
                            ['kind' => 'css_includes', 'value' => 'display: flex'],
                            ['kind' => 'css_includes', 'value' => 'justify-content'],
                            ['kind' => 'css_includes', 'value' => '@media'],
                        ],
                    ],
                    [
                        'title' => 'Hero em duas colunas',
                        'duration' => '15:20',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Cria secao hero com texto e media lado a lado.\n\nRequisitos:\n1. Montar .hero com grid de duas colunas.\n2. Garantir alinhamento vertical entre texto e imagem.\n3. Adaptar para uma coluna em ecras pequenos.",
                        'examples' => [
                            [
                                'label' => 'Hero grid',
                                'language' => 'css',
                                'code' => ".hero { display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; }",
                            ],
                        ],
                        'hint' => 'Use gap e max-width para evitar que o hero fique espremido em desktop.',
                        'html_code' => "<section class=\"hero\">\n  <div class=\"hero-content\">\n    <h2>Aprende UI com CSS</h2>\n    <p>Construcao de interfaces modernas para web.</p>\n    <button class=\"btn-primary\">Ver trilha</button>\n  </div>\n  <div class=\"hero-media\">Preview do produto</div>\n</section>",
                        'css_code' => ".hero-media { border-radius: 12px; background: #e2e8f0; min-height: 220px; }\n",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.hero'],
                            ['kind' => 'selector_exists', 'value' => '.hero-media'],
                            ['kind' => 'css_includes', 'value' => 'grid-template-columns'],
                            ['kind' => 'css_includes', 'value' => '@media'],
                            ['kind' => 'css_includes', 'value' => 'gap'],
                        ],
                    ],
                    [
                        'title' => 'Checkpoint: navegacao e hero',
                        'duration' => '07:00',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Consolida conceitos de header responsivo e composicao hero.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'ui-hero-q1',
                                'question' => 'Qual propriedade distribui itens horizontalmente no flex?',
                                'options' => ['justify-content', 'line-height', 'z-index', 'font-weight'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-hero-q2',
                                'question' => 'Quando usar @media?',
                                'options' => ['para adaptar layout por largura', 'para declarar variavel', 'para validar formulario', 'para executar fetch'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-hero-q3',
                                'question' => 'Qual propriedade cria colunas no hero com grid?',
                                'options' => ['grid-template-columns', 'grid-auto-flow', 'grid-area-name', 'template-gap'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-hero-q4',
                                'question' => 'Gap em layout define...',
                                'options' => ['espaco entre blocos', 'cor de fundo', 'tipo de fonte', 'tempo de transicao'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-hero-q5',
                                'question' => 'Em mobile, geralmente melhor...',
                                'options' => ['reduzir para uma coluna', 'forcar duas colunas fixas', 'ocultar todos os textos', 'aumentar width fixa'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '5. Grid de conteudo e tabela de precos',
                'lessons' => [
                    [
                        'title' => 'Grid de features com auto-fit',
                        'duration' => '15:40',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Monta secao de funcionalidades com cards fluidos.\n\nRequisitos:\n1. Criar .feature-grid com repeat(auto-fit, minmax(...)).\n2. Definir espacamento consistente com gap.\n3. Estilizar .feature-card com borda, padding e hover.",
                        'examples' => [
                            [
                                'label' => 'Feature grid',
                                'language' => 'css',
                                'code' => ".feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }",
                            ],
                        ],
                        'hint' => 'Auto-fit + minmax reduz necessidade de muitos breakpoints.',
                        'html_code' => "<section class=\"feature-grid\">\n  <article class=\"feature-card\">Aulas praticas</article>\n  <article class=\"feature-card\">Projetos reais</article>\n  <article class=\"feature-card\">Certificado final</article>\n</section>",
                        'css_code' => ".feature-card { border: 1px solid #d1d5db; border-radius: 12px; padding: 16px; }\n",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.feature-grid'],
                            ['kind' => 'selector_exists', 'value' => '.feature-card'],
                            ['kind' => 'css_includes', 'value' => 'display: grid'],
                            ['kind' => 'css_includes', 'value' => 'minmax('],
                            ['kind' => 'css_includes', 'value' => ':hover'],
                        ],
                    ],
                    [
                        'title' => 'Tabela de precos com plano em destaque',
                        'duration' => '16:30',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Cria uma tabela de planos com foco no plano principal.\n\nRequisitos:\n1. Estruturar cards de preco com classe .plan-card.\n2. Destacar o plano principal com .plan-popular.\n3. Adicionar hover e micro transicao visual nos planos.",
                        'examples' => [
                            [
                                'label' => 'Plano popular',
                                'language' => 'css',
                                'code' => ".plan-popular { border-color: #2563eb; transform: translateY(-4px); }",
                            ],
                        ],
                        'hint' => 'Evita exagero no destaque: borda forte + leve deslocamento ja comunica prioridade.',
                        'html_code' => "<section class=\"pricing-table\">\n  <article class=\"plan-card\">Starter</article>\n  <article class=\"plan-card plan-popular\">Pro</article>\n  <article class=\"plan-card\">Business</article>\n</section>",
                        'css_code' => ".pricing-table { display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 14px; }\n.plan-card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; transition: transform .2s ease; }",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.pricing-table'],
                            ['kind' => 'selector_exists', 'value' => '.plan-popular'],
                            ['kind' => 'css_includes', 'value' => 'transform'],
                            ['kind' => 'css_includes', 'value' => ':hover'],
                            ['kind' => 'css_includes', 'value' => 'border'],
                        ],
                    ],
                    [
                        'title' => 'Checkpoint: grid e precificacao',
                        'duration' => '07:00',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Consolida composicao de grid e destaque de planos.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'ui-pricing-q1',
                                'question' => 'Para cards responsivos em largura variavel, a melhor abordagem e...',
                                'options' => ['repeat(auto-fit, minmax(...))', 'width fixa para tudo', 'position absolute', 'display table'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-pricing-q2',
                                'question' => 'Qual estrategia destaca plano popular?',
                                'options' => ['classe dedicada com contraste visual', 'esconder outros planos', 'usar fonte 8px', 'desativar hover'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-pricing-q3',
                                'question' => 'Transicao suave em hover melhora...',
                                'options' => ['percepcao de qualidade', 'tempo de build', 'cache do browser', 'seguranca'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-pricing-q4',
                                'question' => 'Gap no grid ajuda...',
                                'options' => ['respiracao visual entre cards', 'mudar tipografia', 'executar JS', 'validar HTML'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-pricing-q5',
                                'question' => 'Uma tabela de precos clara precisa de...',
                                'options' => ['hierarquia e comparacao facil', 'efeitos aleatorios', 'sem titulos', 'sem call to action'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '6. Dashboard UI e acessibilidade',
                'lessons' => [
                    [
                        'title' => 'Cards de metricas com CSS Grid',
                        'duration' => '16:00',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Construi uma secao de metricas para dashboard.\n\nRequisitos:\n1. Criar .stats-grid com grid responsivo.\n2. Estilizar .stat-card com superficie e valor em destaque.\n3. Ajustar breakpoints para manter leitura em mobile.",
                        'examples' => [
                            [
                                'label' => 'Metric cards',
                                'language' => 'css',
                                'code' => ".stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }",
                            ],
                        ],
                        'hint' => 'Prioriza leitura de numero principal com peso e tamanho adequados.',
                        'html_code' => "<section class=\"stats-grid\">\n  <article class=\"stat-card\"><p>Receita</p><strong>1990 MTn</strong></article>\n  <article class=\"stat-card\"><p>Usuarios ativos</p><strong>84</strong></article>\n  <article class=\"stat-card\"><p>Conclusoes</p><strong>71%</strong></article>\n</section>",
                        'css_code' => ".stat-card { border: 1px solid #d1d5db; border-radius: 12px; padding: 14px; }\n",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.stats-grid'],
                            ['kind' => 'selector_exists', 'value' => '.stat-card'],
                            ['kind' => 'css_includes', 'value' => 'display: grid'],
                            ['kind' => 'css_includes', 'value' => 'grid-template-columns'],
                            ['kind' => 'css_includes', 'value' => '@media'],
                        ],
                    ],
                    [
                        'title' => 'Tabela de dados com foco e hover',
                        'duration' => '15:40',
                        'is_free' => false,
                        'type' => 'code',
                        'language' => 'css',
                        'content' => "Melhora a leitura de tabelas no dashboard.\n\nRequisitos:\n1. Estilizar .data-table com border-collapse e alinhamento.\n2. Adicionar estado hover para linhas da tabela.\n3. Garantir foco visivel para links/acoes dentro da tabela.",
                        'examples' => [
                            [
                                'label' => 'Tabela legivel',
                                'language' => 'css',
                                'code' => ".data-table tr:hover { background: #f8fafc; }\n.data-table a:focus-visible { outline: 3px solid #93c5fd; }",
                            ],
                        ],
                        'hint' => 'Em tabela densa, separadores discretos e hover leve ajudam muito a leitura.',
                        'html_code' => "<table class=\"data-table\">\n  <thead><tr><th>Curso</th><th>Conclusao</th><th>Acao</th></tr></thead>\n  <tbody>\n    <tr><td>UI CSS</td><td>82%</td><td><a href=\"#\">Ver</a></td></tr>\n    <tr><td>HTML + CSS</td><td>100%</td><td><a href=\"#\">Ver</a></td></tr>\n  </tbody>\n</table>",
                        'css_code' => ".data-table { width: 100%; border-collapse: collapse; }\n.data-table th, .data-table td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: left; }",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.data-table'],
                            ['kind' => 'css_includes', 'value' => 'border-collapse'],
                            ['kind' => 'css_includes', 'value' => 'tr:hover'],
                            ['kind' => 'css_includes', 'value' => ':focus-visible'],
                        ],
                    ],
                    [
                        'title' => 'Checkpoint: dashboard e acessibilidade',
                        'duration' => '07:00',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Questionario sobre legibilidade de dashboard e estados acessiveis.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'ui-dash-q1',
                                'question' => 'Qual estrutura e comum para metricas em dashboard?',
                                'options' => ['grid de cards', 'lista sem classes', 'iframe unico', 'somente tabela'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-dash-q2',
                                'question' => 'Hover em linha de tabela ajuda...',
                                'options' => ['rastreamento visual da linha', 'compressao de dados', 'reduzir acessibilidade', 'desativar teclado'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-dash-q3',
                                'question' => 'Foco visivel em links e importante para...',
                                'options' => ['navegacao por teclado', 'ocultar elementos', 'renderizar mais rapido', 'evitar HTML semantico'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-dash-q4',
                                'question' => 'Em dashboard, sobrecarga visual deve ser evitada com...',
                                'options' => ['espacos e hierarquia clara', 'cores aleatorias em tudo', 'zero espacamento', 'texto minusculo'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-dash-q5',
                                'question' => 'Qual propriedade ajuda tabela ocupar largura disponivel?',
                                'options' => ['width: 100%', 'position: fixed', 'float: left', 'font-style: table'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '7. Projeto final completo',
                'lessons' => [
                    [
                        'title' => 'Projeto final: UI kit + dashboard responsivo',
                        'duration' => '35:00',
                        'is_free' => false,
                        'type' => 'project',
                        'language' => 'css',
                        'content' => "Entrega final do curso.\n\nRequisitos:\n1. Criar layout completo com header, hero, secao de features, precos e contacto.\n2. Construir area dashboard com cards de metricas e tabela de dados.\n3. Usar design tokens em :root e aplicar em componentes chave.\n4. Garantir estados :hover, :focus-visible e :disabled em elementos interativos.\n5. Tornar tudo responsivo com media query.",
                        'examples' => [
                            [
                                'label' => 'Blocos esperados',
                                'language' => 'html',
                                'code' => "<header class=\"site-header\">...</header>\n<section class=\"hero\">...</section>\n<section class=\"feature-grid\">...</section>\n<section class=\"pricing-table\">...</section>\n<form class=\"contact-form\">...</form>\n<div class=\"dashboard\">...</div>",
                            ],
                        ],
                        'hint' => 'Evolui por etapas: tokens > layout > componentes > estados > ajustes responsivos.',
                        'html_code' => "<header class=\"site-header\">\n  <h1>UrSkool UI Pro</h1>\n  <nav class=\"menu\">\n    <a href=\"#features\">Features</a>\n    <a href=\"#pricing\">Precos</a>\n    <a href=\"#contact\">Contacto</a>\n  </nav>\n  <button class=\"btn-primary\">Comecar</button>\n</header>\n<main>\n  <section class=\"hero\">\n    <div class=\"hero-content\"><h2>UI com CSS de ponta a ponta</h2></div>\n    <div class=\"hero-media\">Preview</div>\n  </section>\n  <section id=\"features\" class=\"feature-grid\">\n    <article class=\"feature-card\">Design tokens</article>\n  </section>\n  <section id=\"pricing\" class=\"pricing-table\">\n    <article class=\"plan-card plan-popular\">Plano Pro</article>\n  </section>\n  <form id=\"contact\" class=\"contact-form\">\n    <label for=\"email\">Email</label>\n    <input id=\"email\" />\n    <button class=\"btn-primary\" disabled>Enviar</button>\n  </form>\n  <div class=\"dashboard\">\n    <section class=\"stats-grid\"><article class=\"stat-card\">Receita</article></section>\n    <table class=\"data-table\"><tr><th>Curso</th></tr><tr><td>UI CSS</td></tr></table>\n  </div>\n</main>",
                        'css_code' => ".site-header { padding: 18px; border-bottom: 1px solid #e5e7eb; }\n.menu { display: flex; gap: 10px; }\n.hero { margin: 20px 0; }\n.feature-card, .plan-card, .stat-card { border: 1px solid #d1d5db; border-radius: 12px; padding: 14px; }\n.data-table { width: 100%; border-collapse: collapse; }\n",
                        'js_code' => '',
                        'validation_rules' => [
                            ['kind' => 'selector_exists', 'value' => '.site-header'],
                            ['kind' => 'selector_exists', 'value' => '.hero'],
                            ['kind' => 'selector_exists', 'value' => '.feature-grid'],
                            ['kind' => 'selector_exists', 'value' => '.pricing-table'],
                            ['kind' => 'selector_exists', 'value' => '.dashboard'],
                            ['kind' => 'selector_exists', 'value' => '.stats-grid'],
                            ['kind' => 'selector_exists', 'value' => '.data-table'],
                            ['kind' => 'css_includes', 'value' => ':root'],
                            ['kind' => 'css_includes', 'value' => 'grid-template-columns'],
                            ['kind' => 'css_includes', 'value' => '@media'],
                            ['kind' => 'css_includes', 'value' => ':focus-visible'],
                        ],
                    ],
                    [
                        'title' => 'Checkpoint final: UI CSS master',
                        'duration' => '07:30',
                        'is_free' => false,
                        'type' => 'quiz',
                        'content' => 'Avaliacao final da trilha completa de UI com CSS.',
                        'quiz_pass_percentage' => 80,
                        'quiz_randomize_questions' => true,
                        'quiz_questions' => [
                            [
                                'id' => 'ui-master-q1',
                                'question' => 'Um bom projeto UI CSS deve priorizar...',
                                'options' => ['consistencia, acessibilidade e responsividade', 'apenas efeitos visuais', 'somente desktop', 'evitar componentes reutilizaveis'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-master-q2',
                                'question' => 'Qual elemento do projeto final valida leitura de dados?',
                                'options' => ['tabela de dashboard', 'favicon', 'meta charset', 'script vazio'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-master-q3',
                                'question' => 'Qual regra ajuda garantir navegacao por teclado?',
                                'options' => [':focus-visible', ':hover', ':active somente', ':visited'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-master-q4',
                                'question' => 'Para escalar interface sem retrabalho, use...',
                                'options' => ['design tokens e componentes', 'inline style em cada bloco', 'cores aleatorias por secao', 'sem classes'],
                                'correctOptionIndex' => 0,
                            ],
                            [
                                'id' => 'ui-master-q5',
                                'question' => 'Media query no projeto final garante...',
                                'options' => ['adaptacao em diferentes larguras', 'execucao de API', 'persistencia local', 'criptografia de senha'],
                                'correctOptionIndex' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function formatLessonContent(array $lessonData, string $lessonType): ?string
    {
        $raw = isset($lessonData['content']) ? trim((string) $lessonData['content']) : '';
        if ($raw === '') {
            return null;
        }

        if (! in_array($lessonType, ['code', 'project'], true)) {
            return $raw;
        }

        if (preg_match('/^\s*</', $raw) === 1) {
            return $raw;
        }

        $parts = preg_split('/\n\s*Requisitos:\s*/u', $raw, 2);
        $theory = trim((string) ($parts[0] ?? ''));
        $requirementsText = trim((string) ($parts[1] ?? ''));
        $steps = $this->extractInstructionSteps($requirementsText);

        if ($steps === []) {
            $steps = [
                'Analisa o codigo inicial e identifica o que precisa de ser melhorado.',
                'Aplica as alteracoes pedidas na descricao da tarefa.',
                'Executa, valida o resultado final e corrige detalhes se necessario.',
            ];
        }

        $examples = $this->normalizeCodeExamples($lessonData['examples'] ?? null);
        $examplesHtml = '';
        if ($examples !== []) {
            $renderedExamples = [];

            foreach ($examples as $index => $example) {
                $title = trim((string) ($example['label'] ?? ''));
                if ($title === '') {
                    $title = 'Exemplo '.($index + 1);
                }

                $language = trim((string) ($example['language'] ?? ''));
                $code = trim((string) ($example['code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $languageTag = $language !== '' ? ' ('.strtoupper(e($language)).')' : '';
                $renderedExamples[] = '<div><p><strong>'.e($title).$languageTag.'</strong></p><pre><code>'.e($code).'</code></pre></div>';
            }

            if ($renderedExamples !== []) {
                $examplesHtml = '<h3>Exemplos</h3>'.implode('', $renderedExamples);
            }
        }

        $hint = trim((string) ($lessonData['hint'] ?? ''));
        if ($hint === '') {
            $hint = 'Faz alteracoes pequenas, executa e confirma cada passo antes de validar.';
        }
        $hintHtml = e($hint);

        $theoryParagraphs = array_filter(array_map('trim', preg_split('/\n{2,}/', $theory) ?: []));
        if ($theoryParagraphs === []) {
            $theoryParagraphs = [$theory];
        }

        $theoryHtml = implode('', array_map(
            static fn (string $paragraph) => '<p>'.e($paragraph).'</p>',
            array_filter($theoryParagraphs, static fn (string $paragraph) => $paragraph !== '')
        ));

        $stepsHtml = implode('', array_map(
            static fn (string $step) => '<li>'.e($step).'</li>',
            $steps
        ));

        return <<<HTML
<h3>Teoria</h3>
{$theoryHtml}
{$examplesHtml}
<h3>Instrucoes da Tarefa</h3>
<ol>
{$stepsHtml}
</ol>
<p><strong>Dica:</strong> {$hintHtml}</p>
HTML;
    }

    private function normalizeCodeExamples(mixed $rawExamples): array
    {
        if (! is_array($rawExamples)) {
            return [];
        }

        $normalized = [];
        foreach ($rawExamples as $index => $example) {
            if (is_string($example)) {
                $code = trim($example);
                if ($code === '') {
                    continue;
                }

                $normalized[] = [
                    'label' => 'Exemplo '.((int) $index + 1),
                    'language' => '',
                    'code' => $code,
                ];

                continue;
            }

            if (! is_array($example)) {
                continue;
            }

            $code = trim((string) ($example['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $normalized[] = [
                'label' => trim((string) ($example['label'] ?? '')),
                'language' => trim((string) ($example['language'] ?? '')),
                'code' => $code,
            ];
        }

        return $normalized;
    }

    private function extractInstructionSteps(string $requirementsText): array
    {
        if ($requirementsText === '') {
            return [];
        }

        $lines = preg_split('/\r?\n/', $requirementsText) ?: [];
        $steps = [];

        foreach ($lines as $line) {
            $clean = trim($line);
            if ($clean === '') {
                continue;
            }

            if (preg_match('/^\d+[\.\)]\s*(.+)$/', $clean, $matches) === 1) {
                $steps[] = trim($matches[1]);

                continue;
            }

            $steps[] = $clean;
        }

        return $steps;
    }

    private function buildWorkspaceFiles(string $htmlCode, string $cssCode, string $jsCode): array
    {
        return [
            [
                'id' => 'index-html',
                'name' => 'index.html',
                'language' => 'html',
                'content' => $htmlCode,
            ],
            [
                'id' => 'style-css',
                'name' => 'style.css',
                'language' => 'css',
                'content' => $cssCode,
            ],
            [
                'id' => 'script-js',
                'name' => 'script.js',
                'language' => 'js',
                'content' => $jsCode,
            ],
        ];
    }

    private function resolveValidationRules(array $lessonData, string $htmlCode, string $cssCode, string $jsCode): array
    {
        $providedRules = LessonCodeValidator::normalizeRules(
            is_array($lessonData['validation_rules'] ?? null) ? $lessonData['validation_rules'] : []
        );

        if ($providedRules !== []) {
            return $providedRules;
        }

        return LessonCodeValidator::deriveValidationRules([
            'html' => $htmlCode,
            'css' => $cssCode,
            'js' => $jsCode,
        ]);
    }
}
