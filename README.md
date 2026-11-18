# ACIC Associados

Plugin WordPress da Jotapix para a lista de associados da ACIC Caçador (portalacic.com.br/associados).

- Cadastro de associados no painel (**Associados**): novo, editar, inativar/reativar e excluir.
- **Importar planilha**: envia a planilha exportada do sistema, confere o que muda e só então aplica. Dá para desfazer a última importação.
- Shortcode `[acic_associados]`: listagem por letra, com busca e filtro A–Z. CNPJ, telefone e WhatsApp aparecem quando preenchidos.

## Importação (passo a passo)

1. **Associados › Importar planilha** e escolha o arquivo (.xlsx ou .csv, do jeito que sai do sistema).
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
- O formato antigo .xls não é lido: salve como .xlsx ou .csv.

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

## Atualizações

Publicar nova versão: subir o `Version` no cabeçalho de `acic-associados.php` (e `ACICA_VERSION`), fazer commit e enviar a tag:

```
git tag v1.0.1 && git push origin v1.0.1
```

O workflow gera o `acic-associados.zip` na release e o site mostra a atualização no painel.

Excluir o plugin não apaga os associados cadastrados, só o histórico de importações e o cache.

---
Jotapix Agência Criativa
