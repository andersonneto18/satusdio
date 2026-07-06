# Comandos Git úteis

```bash
git add .
git commit -m "descrição da alteração"
git push

# ver o que mudou antes de comitar
git status

# ver as alterações linha a linha (o que mudou de facto dentro dos ficheiros)
git diff

# ver o histórico de commits, resumido
git log --oneline

# trazer para o computador alterações feitas direto no GitHub
git pull

# confirmar a que repositório o projeto está ligado
git remote -v

# desfazer alterações não guardadas num ficheiro (volta à última versão comitada)
git checkout -- nome-do-ficheiro

# desfazer o último commit mas manter as alterações nos ficheiros (para corrigir a mensagem, por ex.)
git reset --soft HEAD~1
```
