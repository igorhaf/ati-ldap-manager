# Regras de Negócio – Auditoria de Logs de Operações

## Objetivo

Garantir uma trilha de auditoria confiável e legível por pessoas, que registre de forma inequívoca quem executou qual ação, sobre qual entidade, em qual organização (OU/Município), quando, com qual resultado e o que exatamente mudou.

## Princípios

- **Completude**: toda ação relevante de administração de contas deve gerar registro de log.
- **Clareza**: mensagens em linguagem natural, fáceis de entender por auditores e gestores.
- **Imparcialidade**: o log deve refletir o ocorrido, sem omissões ou interpretações.
- **Imutabilidade**: um registro de log não é editado nem removido; correções geram novos eventos.
- **Privacidade**: dados sensíveis têm tratamento específico (ver seção “Privacidade e Sensibilidade”).
- **Rastreabilidade**: cada evento deve permitir reconstruir o contexto de quem fez, o que fez, sobre quem, onde e quando.

## Escopo de Eventos

Registrar o máximo de eventos administrativos, incluindo, mas não se limitando a:

- Criação de usuário.
- Atualização de usuário (dados cadastrais).
- Redefinição/alteração de senha.
- Desativação/ativação de usuário.
- Atribuição/remoção de papéis (ex.: admin da organização, usuário comum).
- Alterações de dados sensíveis: e-mail principal, login/UID, CPF, status, OU (organização do usuário).
- Inclusão/remoção de e-mails.
- Alterações de OUs do usuário (adição/remoção/troca de OU e papel).
- Operações em lote (ex.: via carga de arquivo) – ver regra específica mais abaixo.

## Conteúdo Mínimo de Cada Registro de Log

Cada evento de auditoria deve registrar, no mínimo:

- **Operação realizada**: ação de negócio (ex.: criar usuário, atualizar usuário, redefinir senha, desativar, ativar, adicionar papel, remover papel).
- **Entidade alvo**: quem/qual entidade foi impactada (ex.: usuário afetado) e seu identificador de negócio (ex.: login/UID).
- **Organização (OU)**: organização/município a que a ação se aplica.
- **Ator**: quem executou a ação (usuário autenticado) e seu **perfil** (root, admin de organização, etc.).
- **Data/Hora**: momento do evento em horário oficial do sistema.
- **Resultado**: sucesso ou falha.
  - Em caso de falha, **mensagem de erro compreensível** para auditoria.
- **Resumo do que mudou**: lista dos campos/atributos alterados, quando aplicável.
- **Detalhamento das mudanças**: para cada campo alterado, registrar o **antes → depois** quando fizer sentido de negócio.

## Regras por Tipo de Operação

- **Criação de usuário**
  - Registrar todos os dados essenciais definidos no cadastro inicial.
  - Indicar OU(s) de associação no momento da criação e papel(éis) atribuídos.
- **Atualização de usuário**
  - Registrar somente o que mudou, com antes/depois por campo relevante.
  - Destacar alterações sensíveis (ver seção específica).
- **Redefinição de senha**
  - Registrar que houve mudança de senha para o usuário alvo.
  - Nunca registrar valores de senha.
  - Indicar quem solicitou (ator) e sobre quem foi aplicada (alvo).
- **Desativação/ativação**
  - Registrar a mudança de status do usuário, com antes/depois (ex.: ativo → inativo).
- **Atribuição/remoção de papéis**
  - Registrar papel adicionado/removido e a OU em que se aplica.
- **Alteração de OU**
  - Registrar inclusão/remoção/troca de OU e papel correspondente.
- **Operações em lote** (ex.: importações)
  - Registrar um **evento-resumo** com o total de itens processados, sucesso e falhas.
  - Registrar **um evento por entidade afetada**, mantendo rastreabilidade individual.

## Alterações Sensíveis (Tratamento Específico)

- **CPF**
  - Registrar antes/depois da alteração.
  - Exibição completa para perfis autorizados (ex.: root). Para outros perfis, aplicar regra de ofuscação quando houver exigência de privacidade institucional.
- **Login/UID**
  - Registrar antes/depois, pois impacta identificação do usuário.
- **E-mail principal**
  - Registrar antes/depois.
- **Status (ativo/inativo/bloqueado)**
  - Registrar antes/depois.
- **OU e papel**
  - Registrar a alteração de OU e o papel associado (antes/depois).
- **Senha**
  - Registrar apenas a ocorrência da troca e o ator/alvo. **Nunca** registrar valores de senha.

## Resultado e Mensagens de Erro

- Em caso de **sucesso**: descrever a ação em linguagem clara, incluindo o usuário alvo (identificador de negócio) e, quando fizer sentido, um resumo dos campos alterados.
- Em caso de **falha**: registrar a ação pretendida, o alvo, a OU, o ator e **uma mensagem de erro compreensível** que permita entender o motivo do insucesso.

## Visibilidade e Acesso aos Logs

- **Root**: acesso a todos os registros de todas as OUs.
- **Admin de organização (OU)**: acesso aos registros relacionados à sua OU.
- **Usuário comum**: não possui acesso aos logs administrativos (salvo política específica em contrário).

## Consulta e Relatórios

- Permitir filtros por período, operação, resultado, OU, ator, alvo e palavras‑chave da descrição.
- Permitir ordenação por data/hora decrescente por padrão.
- Permitir exportação dos resultados para fins de auditoria, observando a política de privacidade vigente.

## Privacidade e Sensibilidade

- **Nunca** registrar segredos ou credenciais.
- Aplicar **ofuscação** quando necessário por política institucional (ex.: exibir apenas parte do CPF para perfis não autorizados).
- **Dados pessoais** devem aparecer apenas quando justificados por necessidade de auditoria e conforme o perfil do consultante.

## Retenção e Conservação

- Manter os logs pelo **prazo definido pela organização** para auditoria e conformidade (recomendação comum: mínimo de 5 anos), salvo normas internas específicas.
- Registros são **imutáveis**; retificações ou correções são lançadas como **novos eventos** vinculados logicamente ao evento original.

## Qualidade dos Registros

- Mensagens devem ser consistentes, sem abreviações obscuras.
- O horário deve refletir a configuração oficial do sistema e ser consistente em todas as consultas.
- O identificador de negócio do alvo (ex.: login/UID) deve estar sempre presente para permitir rastreio.

## Exemplos de Mensagens (ilustrativos)

- "Usuário joao.silva atualizado na OU moreno: e-mail alterado, status mantido."
- "Senha redefinida para o usuário maria.santos na OU treinamento por admin de organização."
- "Papel de administrador atribuído ao usuário alunoadm05 na OU treinapref."

---

Estas regras visam garantir uma trilha de auditoria completa, clara e proporcional, atendendo às necessidades de governança, conformidade e investigação, sem expor informações sensíveis além do necessário.
