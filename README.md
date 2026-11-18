# ACIC Associados

Plugin WordPress da Jotapix para a lista de associados da ACIC Caçador (portalacic.com.br/associados).

- Cadastro de associados no painel (**Associados**): novo, editar, inativar/reativar e excluir.
- **Importar planilha**: envia a planilha exportada do sistema, confere o que muda e só então aplica. Dá para desfazer a última importação.
- Shortcode `[acic_associados]`: listagem por letra, com busca e filtro A–Z. CNPJ, telefone e WhatsApp aparecem quando preenchidos.
- **Ajuda** (Associados › Ajuda): guia com telas anotadas e animações, quadro de "Primeiros passos" e botões **?** com dicas nos campos.

## Importação (passo a passo)

1. **Associados › Importar planilha** e escolha o arquivo (.xls, .xlsx ou .csv, do jeito que sai do sistema).
2. Diga o que tem na planilha:
   - **A lista completa**: quem não estiver nela fica **inativo** (sai do site, continua no painel).
   - **Só alguns associados**: adiciona e atualiza sem tirar ninguém do site.
3. Confira a prévia (novos, atualizados, que voltam, que saem) e clique em **Aplicar alterações**.
4. Se algo saiu errado: **Desfazer a última importação**.

### Colunas reconhecidas

O cabeçalho pode estar em qualquer ordem, com ou sem acento:

| Campo | Nomes aceitos |
| --- | --- |
| Razão social (obrigatório) | NOME, RAZÃO, RAZÃO SOCIAL, EMPRESA |
| Nome fantasia | NOMEFANTASIA, NOME FANTASIA, FANTASIA |
| CNPJ | CNPJ, CNPJ/CPF |
| Telefone | TELEFONE, FONE |
| WhatsApp | WHATSAPP, CELULAR |

### Regras

- Linhas repetidas viram um associado só (o nome é comparado sem acento, pontuação e sufixos como LTDA, ME, EIRELI, S/A).
- Com CNPJ na planilha, a comparação usa o CNPJ (empresas com o mesmo nome e CNPJs diferentes ficam separadas).
- Campo vazio na planilha nunca apaga o que já está cadastrado no painel.
- Associado com **Manter sempre ativo** marcado nunca é inativado pela importação.
- Cada CNPJ é um associado: filiais aparecem separadas, cada uma com o seu CNPJ.
- CPF na coluna CNPJ (pessoa física) é ignorado: não é guardado nem aparece no site.
- Arquivos .xls exportados pelo sistema (que por dentro são .xlsx) são lidos normalmente. Só o .xls antigo do Excel 97-2003 precisa ser salvo como .xlsx ou .csv.

## Shortcode

```
[acic_associados]
```

| Atributo | Padrão | Efeito |
| --- | --- | --- |
| `total` | `sim` | Frase com o total de associados no topo |
| `busca` | `sim` | Campo de busca |
| `letras` | `sim` | Botões A–Z |

Exemplo: `[acic_associados total="nao"]`.

No Divi, coloque o shortcode num módulo **Texto** (ou **Código**). A listagem fica em cache e é atualizada sozinha quando um associado é salvo ou importado.

## Guia (manual)

- Texto: `manual/fonte.html` (o mesmo modelo do guia do JPX Eventos).
- Capturas e animações: `ferramentas/capturas-manual.py`, num WordPress de **testes** em português, preparado com `ferramentas/dados-demo.php` (nunca rode no site da ACIC).
- Depois de editar o texto ou trocar imagens: `python3 ferramentas/montar-manual.py` gera o `manual/index.html` que o painel mostra.
- `ferramentas/` e `manual/fonte.html` não vão no .zip.

## Versões

- **1.1.1**: atualizações automáticas pelo repositório `julcenei/plugin-acic-associados`.
- **1.1.0**: guia de uso no painel (Associados › Ajuda), Primeiros passos e dicas "?"; lê a planilha .xls exportada pelo sistema (que é .xlsx por dentro).
- **1.0.0**: primeira versão.

## Atualizações

Para lançar uma versão: altere `Version:` no cabeçalho de `acic-associados.php` **e** `ACICA_VERSION` (iguais), registre a versão no topo do `changelog.txt` e envie para a `main`. O workflow `.github/workflows/release.yml` cria a tag, gera o `acic-associados.zip` e publica a release em `julcenei/plugin-acic-associados`. Não é preciso criar tags à mão. O site mostra a atualização em **Plugins**.

Excluir o plugin não apaga os associados cadastrados, só o histórico de importações e o cache.

---
Jotapix Agência Criativa
