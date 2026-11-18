# ACIC Associados — instruções para o Claude

Plugin WordPress da Jotapix, só para o site da ACIC Caçador. O dono (Julcenei) não quer cuidar do processo do GitHub: **o Claude cuida de tudo**.

## Ao terminar qualquer alteração no plugin

Pergunte, em uma linha, se ele quer publicar no GitHub, sugerindo o número da próxima versão. Exemplo:
"Quer que eu publique isso como versão 1.1.2?"

Se ele disser sim (ou já tiver pedido "atualiza no github", "publica", "sobe"), faça tudo sem pedir mais nada:

1. Escolha a versão (semver): correção → patch; recurso novo → minor; quebra de compatibilidade → major (confirme antes).
2. Em `acic-associados.php`, altere `Version:` no cabeçalho **e** `ACICA_VERSION` (os dois iguais).
3. No topo de `changelog.txt`, adicione a entrada no formato existente (`1.1.2 — DD/MM/AAAA` e linhas `- …`, do ponto de vista de quem usa).
4. Verifique: `php -l` nos PHP alterados e `sh ferramentas/notas-versao.sh <versão>` (precisa imprimir as notas). Se mudou o texto do guia, rode `python3 ferramentas/montar-manual.py`.
5. Commit (mensagem em português) e `git push origin main`. **Não crie nem envie tags**: `.github/workflows/release.yml` cria a tag, gera o `acic-associados.zip` e publica a release.
6. Responda em linguagem simples: o que foi publicado e o número da versão.

Se o push falhar, explique em poucas palavras. Nunca peça que ele rode comandos de git.

## Referências

- Estrutura e regras de importação: `README.md`.
- Guia do painel: `manual/fonte.html` → `manual/index.html` (ferramentas em `ferramentas/`).
- Textos visíveis em português do Brasil, text domain `acic-associados`.
- Nunca rode `ferramentas/dados-demo.php` no site da ACIC: ele apaga os associados.
