# Formulaire des notices d’archéologie médiévale

Plugin WordPress du formulaire de soumission des notices d'opération pour la
Chronique d'*Archéologie médiévale*. Le code du plugin est dans
`notice-archeomed/` ; sa documentation d'usage y est restée, c'est elle
qu'on lit pour installer et régler.

## Vérifier avant d'installer

```sh
./verifier-la-syntaxe notice-archeomed/*.php
```

Il n'y a pas de PHP sur le poste où ce plugin s'écrit : « php -l » n'est pas
jouable. Ce contrôle ne le remplace pas — il attrape l'accolade ou
l'apostrophe restée ouverte, rien de plus. **Un passage vert ne dispense pas
d'installer sur un site d'essai avant la production.**

## Vérifier les styles après un changement de gabarit

```sh
./verifier-les-styles
```

Rapproche ce que le code applique et ce que le gabarit livré contient. À
passer chaque fois qu'on remplace `modele-metopes.docx` : un style disparu ne
provoque aucune erreur, le paragraphe sort simplement en Normal.

S'il trouve un dossier `reference/` portant un fichier de stylage, il le
compare aussi à celui-ci ; sans ce dossier, il travaille avec le gabarit seul.

## Fabriquer l'archive d'installation

```sh
./empaqueter
```

Écrit `notice-archeomed.zip` à la racine, prêt pour
« Extensions ▸ Ajouter ▸ Téléverser une extension ».
