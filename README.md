Pequeño bot de ejemplo usando phptdlib basado en TDLib
  
Antes de empezar revisa los archivos `*.sample`, rellena y cambia los datos
necesarios y renombralos para que queden sin el `.sample`.
  
El resto es descargar, compilar y ejecutar con [phptdlib](https://github.com/yaroslavche/phptdlib)
  
O usar docker con `docker pull fabianpastor/phptdlib`.  
Si usas el docker de arriba bajo linux solo tienes que clonar este repositorio en una carpeta de tu pc(en mi caso `~/tdlib/bots/testbot/`) y ejecutar el siguiente comando para dar tus primeros pasos:  
\> `docker run -it --name testbot -v ~/tdlib/bots/testbot:/app/ --workdir /app --entrypoint "/app/bot.php" fabianpastor/phptdlib`  
Para parar el bot usa  
\> `docker kill testbot`
