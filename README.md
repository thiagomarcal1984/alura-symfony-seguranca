# Criando as entidades
O comando do console para criar entidades/repositórios é o mesmo para modificá-las! Veja:
```
php .\bin\console make:entity Series 

 Your entity already exists! So let's add some new fields!

 New property name (press <return> to stop adding fields):
 >
```

# Atualizando o cadastro
Objetos de transferência de dados (DTO): o DTO `SeriesCreateFromInput` vai servir para guardar várias informações de uma série, para posterior atualização do banco de dados. Com esse DTO, criaremos várias linhas de 3 entidades diferentes (Episode, Season e Series). É uma classe MUITO simples, com um construtor que contém apenas propriedades públicas (não contém métodos).

O DTO `SeriesCreateFromInput` será o tipo padrão no `SeriesType`, o formulário do Symfony baseado até então na classe `Series`:
```php
<?php

namespace App\Form;

use App\DTO\SeriesCreateFromInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeriesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                child: 'seriesName', 
                options: [ 'label' => 'Nome:' ]
            )
            ->add(
                'seasonsQuantity', 
                NumberType::class, 
                options: [ 'label' => 'Qtd Temporadas:' ]
            )
            ->add(
                child: 'episodesPerSeason', 
                type: NumberType::class, 
                options: [ 'label' => 'Ep por Temporada:' ]
            )
            // Agora os campos são os mesmos do DTO SeriesCreateFromInput::class.
            ->add(
                'save', 
                SubmitType::class, 
                [ 'label' => $options['is_edit'] ? 'Editar' : 'Adicionar' ]
            )
            ->setMethod($options['is_edit'] ? 'PATCH' : 'POST')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SeriesCreateFromInput::class, // Antes era Series::class.
            'is_edit' => false, 
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
```

Modificado o formulário `SeriesType`, vamos alterar o código no controller:
```php
    #[Route('/series/create', name: 'app_series_form', methods: ['GET'])]
    public function addSeriesForm() : Response {
        $seriesForm = $this->createForm(
            SeriesType::class, 
            new SeriesCreateFromInput() // Antes era o construtor de Series.
        );
        return $this->renderForm('/series/form.html.twig', compact('seriesForm'));
    }

    #[Route('/series/create', name: 'app_add_series', methods: ['POST'])]
    public function addSeries(Request $request) : Response {
        $input = new SeriesCreateFromInput();
        $seriesForm = $this->createForm(SeriesType::class, $input)
            ->handleRequest($request)
        ;
        if(!$seriesForm->isValid()) {
            return $this->renderForm('series/form.html.twig', compact('seriesForm'));
        }

        // Lógica para a inserção das séries, temporadas e episódios.
        $series = new Series($input->seriesName); // Criou a série.

        for ($i = 1; $i <= $input->seasonsQuantity; $i++) {
            $season = new Season($i); // Criou a temporada.
            for ($j=1; $j <= $input->episodesPerSeason; $j++) { 
                $season->addEpisode(new Episode($j)); // Criou o episódio.
            }
            $series->addSeason($season);
        }

        // A inserção é feita em cascata. As entidades precisaram ser alteradas
        $this->seriesRepository->save($series, true);
        
        $this->addFlash(
            'success', 
            "Série \"{$series->getName()}\" incluída com sucesso."
        );
        return new RedirectResponse('/series');
    }
```

O código da entidade `Series` mudou para possibilitar a inserção em cascata:
```php
#[ORM\Entity(repositoryClass: SeriesRepository::class)]
class Series
{
    #[ORM\Id]#[ORM\GeneratedValue]#[ORM\Column]
    private int $id;

    #[ORM\OneToMany(
        mappedBy: 'series',
        targetEntity: Season::class, 
        orphanRemoval: true,
        // Persistência em cascata de temporadas.
        cascade: ['persist'] 
    )]
    private Collection $seasons;

    // ...
}
```

O mesmo para o código da entidade `Season`:
```php
class Season
{
    #[ORM\Id]#[ORM\GeneratedValue]#[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\OneToMany(
        mappedBy: 'season', 
        targetEntity: Episode::class, 
        orphanRemoval: true,
        // Persistência em cascata de episódios.
        cascade: ['persist']
    )]
    private Collection $episodes;

    #[ORM\ManyToOne(inversedBy: 'seasons')]
    // Repare a referencia ao atributo 'seasons' da entidade 'Series'.
    #[ORM\JoinColumn(nullable: false)]
    private Series $series;

    // ...
}
```
Código do Twig do formulário:
```php
{% extends 'base.html.twig' %}
{#
    Como este código está comentado, o título padrão 
    do bloco title será usado:
    {% block title %}Título Específico{% endblock %}
#}

{% block title %}{{ series is defined ? 'Editar' : 'Nova' }} Série{% endblock %}

{% block body %}
    {{ form_start(seriesForm) }} 
    {{ form_row(seriesForm.seriesName) }}
    {{ form_row(seriesForm.seasonsQuantity) }}
    {{ form_row(seriesForm.episodesPerSeason) }}
    {{ form_widget(seriesForm.save, {'attr' : {'class' : 'btn-dark' } }) }}
    {{ form_end(seriesForm) }} 
{% endblock %}
```

Repare no HTML gerado para os campos numéricos (seriesQuantity e episodesPerSeason). Eles ganharam mais um atributo chamado `inputmode="decimal"`. Dessa forma, a aplicação exibiria no smartphone o tipo de entrada correto (somente com números):
```HTML
    <div class="mb-3">
        <label for="series_seasonsQuantity" class="form-label required">
            Qtd Temporadas:
        </label>
        <input
            type="text"
            id="series_seasonsQuantity"
            name="series[seasonsQuantity]"
            required="required"
            inputmode="decimal"
            class="form-control"
            value="0" 
        />
    </div>
```
Finalmente, os arquivos de migrations foram atualizados para 2 migrations: a primeira que contém apenas a criação da entidade Series, e a segunda que cria as demais entidades.

> O Symfony insiste em criar a linha `$this->addSql('CREATE SCHEMA public')` nos métodos `down($schema)` das migrations. Mas o problema é que o esquema já está criado, independente de qual migration estivermos. Por isso, esse código foi comentado:
```php
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Código comentado para impedir tentativa de recriar o schema public:
        // $this->addSql('CREATE SCHEMA public'); 
        // ... restante do código das migrations.
    }
 ```

# Exibindo os dados
O controller foi criado usando o comando da CLI do Symfony:
```
php .\bin\console make:controller SeasonsController
```
O código do Controller, após as alterações, ficou assim: 
```php
<?php

namespace App\Controller;

use App\Entity\Series;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SeasonsController extends AbstractController
{
    #[Route('/series/{series}/seasons', name: 'app_seasons')]
    public function index(Series $series): Response
    {
        $seasons = $series->getSeasons();

        return $this->render('seasons/index.html.twig', [
            'seasons' => $seasons,
            'series' => $series,
        ]);
    }
}
```
Template Twig para a ação `index` em `SeasonsController` (repare no filtro `length` nas badges para contar os episódios):
```php
{% extends 'base.html.twig' %}

{% block title %}
    Temporadas da série "{{ series.name }}"
{% endblock %}

{% block body %}
<ul class="list-group">
    {% for season in seasons %}
    <li class="list-group-item d-flex justify-content-between">
        Temporada {{ season.number }}
        <span class="badge text-bg-secondary">{{ season.episodes | length }}</span>
    </li>
    {% endfor %}
</ul>
{% endblock %}
```

Template Twig para a ação `index` em `SeriesController`
```php
        {% for series in seriesList %}
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <a href="{{ path('app_seasons', { series : series.id }) }}">
                    {#
                        /* Compare os parâmetros fornecidos na função path 
                        com o da assinatura em SeasonsController:
                        
                        #[Route('/series/{series}/seasons', name: 'app_seasons')]
                        public function index(Series $series): Response {...}
                        */
                    #}
                    {{ series.name }}
                </a>
                {# ... Restante do código ... #}
            </li>
        {% endfor %}

```
# Conceito de cache
É um local em que a busca por informações é mais rápida do que se você buscasse da fonte original. Os dados em cache tem a desvantagem de nem sempre refletirem o conteúdo da fonte original, além de não serem dados persistentes (são geralmente guardados em memória RAM).

# Cacheando as temporadas
O código abaixo explica como usar o cache para cada objeto usado na action index do controlador de temporadas:
```php
<?php

namespace App\Controller;

use App\Entity\Series;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SeasonsController extends AbstractController
{
    public function __construct(private CacheInterface $cache)
    {}

    #[Route('/series/{series}/seasons', name: 'app_seasons')]
    public function index(Series $series): Response
    {
        $seasons = $this->cache->get(
            // String que representa a chave do que vai ser buscado.
            "series_{$series->getId()}_seasons", 
            function (ItemInterface $item) use ($series) { 
                // Função caso não ache a chave no cache.
                // O comando use passa o parâmetro $series para o bloco.

                $item->expiresAfter(new \DateInterval(duration: 'PT10S'));
                // A barra em \DateInterval é para não precisar de 
                // importar usando o comando use. PT10S significa 10 segundos.
                
                /** @var PersistentCollection $seasons 
                 * Sem a anotação acima, o método initialize fica inacessível.
                */
                $seasons = $series->getSeasons();
                // Garantir que a coleção é inicializada antes de guardar no cache.
                $seasons->initialize(); 

                return $seasons;
            }
        );

        return $this->render('seasons/index.html.twig', [
            'seasons' => $seasons,
            'series' => $series,
        ]);
    }
}
```

Depois que usamos o `$seasons->initialize()` em index, os nomes das temporadas ficam guardados. No entanto, a contagem de episódios fica zerada.

# Para saber mais: PSRs
O Symfony suporta as PSRs 6 e 16 para controle de cache. Os recursos de cache do Symfony do código deste projeto possui implementação diferente das PSRs. Se for usar cache, tente usar as PSRs por questões de compatibilidade futuras.

# Configurações
O Symfony separa os caches conforme seu ambiente (desenvolvimento ou produção). Assim, pastas diferente são criadas dentro do diretório cache para cada ambiente.

Os caches contém pools, que agrupam elementos cacheáveis. O cache "app" dentro `cache/{ambiente}/pools` armazena os dados no sistema de arquivos por padrão.

O arquivo `config\packages\cache.yaml` contém as configurações de cache do Symfony. Para mais detalhes, consulte a documentação: https://symfony.com/doc/current/components/cache.html 

# Doctrine cache
O arquivo `config\packages\doctrine.yaml` possibilita a configuração dos 3 tipos de cache do Doctrine:
1. metadata_cache_driver (mapeamento ORM);
2. query_cache_driver (consultas DQL); e
3. result_cache_driver (resultados das consultas).

```yaml
doctrine:
    orm:
        # Configura o cache de metadados do Doctrine, 
        # ou qualquer um dos outros dois tipos de cache.
        metadata_cache_driver:
            # O cache fica dentro de pool.
            type: pool
            # O pool pode ser cache.app ou cache.system.
            pool: cache.app
```

É possível fazer um cache completo das entidades do Doctrine usando o Second Level Cache (Cache de Segundo Nível):

```yaml
doctrine:
    orm:
        second_level_cache:
            # Habilitar o Cache de Segundo Nível (SLC).
            enabled: true 
            # Mesma coisa que os 3 caches do Doctrine.
            region_cache_driver:    
                type: pool
                pool: cache.app
```
Existem configurações diferentes para outros ambientes (teste, produção etc.). O exemplo aqui foi usado para ambiente de desenvolvimento. Veja as partes do código com os textos `when@test` e `when@prod` no arquivo `config\packages\doctrine.yaml`.

Aplicação do cache de segundo nível nas entidades e em seus relacionamentos:
```php
#[ORM\Cache] // Habilita o Cache de Segundo Nível na entidade.
class Episode
{ /*... resto do código... */ }
```

```php
#[ORM\Cache] // Habilita o Cache de Segundo Nível na entidade.
class Season
{ 
    /*... resto do código... */ 
    #[ORM\Cache] // Habilita o Cache de Segundo Nível no relacionamento.
    private Collection $episodes;

    /*... resto do código... */ 
}
```

```php
#[ORM\Cache] // Habilita o Cache de Segundo Nível na entidade.
class Series
{
    /*... resto do código... */
    #[ORM\Cache] // Habilita o Cache de Segundo Nível no relacionamento.
    private Collection $seasons;

    /*... resto do código... */ 
}
```

# Adicionando propriedade
Vamos acrescentar o campo `watched` na entidade `Episode`, usando a CLI do Symfony:
```
php .\bin\console make:entity Episode
 Your entity already exists! So let's add some new fields!

 New property name (press <return> to stop adding fields):
 > watched

 Field type (enter ? to see all types) [string]:
 > boolean

 Can this field be null in the database (nullable) (yes/no) 
[no]:
 > no

 updated: src/Entity/Episode.php

 >


 
  Success! 
 

 Next: When you're ready, create a migration with php bin/console make:migration
```
A coluna da entidade ficaria assim: 
```php
class Episode
{
    /*... resto do código... */ 

    #[ORM\Column]
    // private ?bool $watched = null; // Código gerado pelo Symfony.
    private bool $watched = false; // Código modificado por mim.

    /*... resto do código... */ 
}
```

Alterada a entidade, criamos uma nova migração usando o comando:
```
php .\bin\console make:migration
```
Altere o arquivo da migration no que for necessário (por exemplo, removendo a linha `$this->addSql('CREATE SCHEMA public');`).

Finalmente, execute a migração:
```
php .\bin\console doctrine:migrations:migrate
```

Agora, a criação de `EpisodesController`:
```
php .\bin\console make:controller EpisodesController

 created: src/Controller/EpisodesController.php
 created: templates/episodes/index.html.twig

 
  Success! 
 

 Next: Open your new controller class and add some pages!
 ```

Segue novo código para o controller:
```php
<?php

namespace App\Controller;

use App\Entity\Season;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EpisodesController extends AbstractController
{
    #[Route('/season/{season}/episodes', name: 'app_episodes')]
    public function index(Season $season): Response
    {
        return $this->render('episodes/index.html.twig', [
            'season' => $season,
            'series' => $season->getSeries(),
            'episodes' => $season->getEpisodes(),
        ]);
    }
}
```
Template Twig da ação index de `EpisodesController`:
```php
{% extends 'base.html.twig' %}

{% block title %}
    Episódios da temporada {{ season.number }} da série {{ series.name }}
{% endblock %}

{% block body %}
    <ul class="list-group">
        {% for episode in episodes %}
            <li class="list-group-item">Episódio {{ episode.number }}</li>
        {% endfor %}
    </ul>
{% endblock %}
```
Adaptação do template Twig da ação index de SeasonsController. **Note que dentro do link fornecemos como parâmetro o id da season, não a season toda**:
```php
<ul class="list-group">
    {% for season in seasons %}
    <li class="list-group-item d-flex justify-content-between">
        <a href="{{ path('app_episodes', { season: season.id }) }}">
            Temporada {{ season.number }}
        </a>
        <span class="badge text-bg-secondary">{{ season.episodes | length }}</span>
    </li>
    {% endfor %}
</ul>
```
Finalmente, as migrations foram alteradas para permitir a exclusão em cascata das entidades com relacionamentos.

# Criando o formulário
O HTML permite envio de dados em formato de array, no caso de checkboxes. Para isso, nomeie os checkboxes com o nome do array e dentro dele coloque o valor. Veja no código do template Twig do index de `EpisodesController`:
```php
<form method="post">
    <ul class="list-group">
        {% for episode in episodes %}
            <li class="list-group-item d-flex justify-content-between">
                <label for="episodes[{{episode.id}}]" class="container-fluid">
                    Episódio {{ episode.number }}
                </label>
                <input type="checkbox" name="episodes[{{episode.id}}]" id="episodes[{{episode.id}}]">
            </li>
        {% endfor %}
    </ul>
    <button class="btn btn-primary my-2">
        Salvar
    </button>
</form>
```
Perceba como nomeamos cada checkbox com `episodes[{{episode.id}}]`. O tipo `episodes` é um tipo não escalar, ou seja, é um tipo composto. Tipos não escalares são obtidos por meio do método `all` no Symfony:

```php
class EpisodesController extends AbstractController
{
    #[Route('/season/{season}/episodes', name: 'app_watch_episodes', methods: ['POST'])]
    public function watch(Season $season, Request $request): Response
    {
        // Retornaria um dado escalar: vai quebrar.
        // dd($request->request->get('episodes')); 
        
        // Retorna um array, um dado não escalar.
        // dd($request->request->all('episodes')); 
        
        // Queremos só os IDs, não o status de cada episódio.
        dd(array_keys($request->request->all('episodes'))); 

        return $this->redirectToRoute('app_episodes');
    }
}
```

# Salvando no banco
Atualização da lógica de atualização da lista de episódios assistidos, que está em `EpisodesController`:
```php
class EpisodesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /*... resto do código... */ 

    #[Route(
        '/season/{season}/episodes',
        name: 'app_watch_episodes',
        methods: ['POST']),
    ]
    public function watch(Season $season, Request $request): Response
    {
        $watchedEpisodes = array_keys(
            $request->request->all('episodes')
        );
        $episodes = $season->getEpisodes();

        foreach ($episodes as $episode) {
            // Se o ID estiver na lista de assistidos, marca como true.
            $episode->setWatched(
                in_array($episode->getId(), $watchedEpisodes)
            );
        }

        $this->entityManager->flush();

        $this->addFlash(
            "success",
            "Episódios marcados como assistidos.",
        );

        return $this->redirectToRoute(
            'app_episodes', 
            ['season' => $season->getId()],
        );
        /* 
        return new RedirectResponse(
            "/season/{$season->getId()}/episodes"
        );
        */
    }
}
```

Atualização do formulário, para mostrar quais episódios foram ou não assistidos:
```php
<form method="post">
    <ul class="list-group">
        {% for episode in episodes %}
            <li class="list-group-item d-flex justify-content-between">
                <label for="episodes[{{episode.id}}]" class="container-fluid">
                    Episódio {{ episode.number }}
                </label>
                <input type="checkbox" 
                    name="episodes[{{episode.id}}]" 
                    id="episodes[{{episode.id}}]"
                    {% if episode.watched %} checked {% endif %}
                />
            </li>
        {% endfor %}
    </ul>
    <button class="btn btn-primary my-2">
        Salvar
    </button>
</form>
```
Perceba o `{% if episode.watched %} checked {% endif %}` no checkbox.

O L2C (SLC, Cache de Segundo Nível) do Doctrine foi desabilitado para facilitar o desenvolvimento.

# Episódios assistidos
Mudanças na entidade `Season`:
```php
class Season
{
    /*... resto do código... */ 
    /**
     * @return Collection<int, Episode>
     */
    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    /**
     * @return Collection<int, Episode>
     */
    public function getWatchedEpisodes(): Collection
    {
        return $this->episodes->filter(
            fn(Episode $episode) => $episode->isWatched()
        );
    }
    /*... resto do código... */ 
}
```
Perceba o método `filter` dentro da coleção de episodes: ela recebe como parâmetro um objeto do tipo `Clojure`, que nada mais é do que uma função lambda. Uma `Clojure` é criada usando a função `fn`. A partir daí, a sintaxe é muito semelhante à do JavaScript.

Alteração da badge mostrando a proporção de episódios assistidos:
```php
<span class="badge text-bg-secondary">
    {{ season.watchedEpisodes | length }} / {{ season.episodes | length }}
</span>
```
# Criando entidade User
O usuário no Symfony é um objeto de infraestrutura, não um objeto de domínio. Caso você precise incluir o usuário como classe de domínio, adapte a classe para tal.

A criação de um usuário no Symfony pode ser feita via CLI:
```
 php .\bin\console make:user

 The name of the security user class (e.g. User) [User]:
 >

 Do you want to store user data in the database (via Doctrine)? (yes/no) [yes]:
 > 

 Enter a property name that will be the unique "display" name for the user (e.g. email, username, uuid) [email]:        
 > 

 Will this app need to hash/check user passwords? Choose No 
if passwords are not needed or will be checked/hashed by some other system (e.g. a single sign-on server).

 Does this app need to hash/check user passwords? (yes/no) [yes]:
 >

 created: src/Entity/User.php
 created: src/Repository/UserRepository.php
 updated: src/Entity/User.php
 updated: config/packages/security.yaml

 
  Success! 
 

 Next Steps:
   - Review your new App\Entity\User class.
   - Use make:entity to add more fields to your User entity 
and then run make:migration.
   - Create a way to authenticate! See https://symfony.com/doc/current/security.html
```

Após a criação do usuário, o arquivo `config\packages\security.yaml` é modificado para conter as referências ao novo usuário criado e ao provider de usuário (banco de dados, LDAP etc.).

A classe User implementa duas interfaces:
1. UserInterface (para obter a identificação do usuário; força a implementação dos métodos `getRoles()`, `eraseCredentials()`e `getUserIdentifier()`); e
2. PasswordAuthenticatedUserInterface (para lidar com a senha; força a implementação do método `getPassword()`).

A convenção para nomear as roles de usuários é `ROLE_<nome do papel>`.

O comando `doctrine:migrations:migrate` roda todas as migrações que não foram executadas ainda.

> Não custa reforçar: o código gerado pelo framework de migrations do Doctrine nem sempre é funcional. Teste **SEMPRE** a atualização e o rollback da migration.

# Formulário de login
Mais detalhes na documentação: https://symfony.com/doc/current/security.html

Para criar o formulário de login, crie o controller correspondente:
```
php .\bin\console make:controller Login                 

 created: src/Controller/LoginController.php
 created: templates/login/index.html.twig

 
  Success! 
 

 Next: Open your new controller class and add some pages!
 ```
 
 Código do arquivo `config\packages\security.yaml`:
 ```YAML
security:
    # https://symfony.com/doc/current/security.html#registering-the-user-hashing-passwords
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
    # https://symfony.com/doc/current/security.html#loading-the-user-the-user-provider
    providers:
        # used to reload user from session & other features (e.g. switch_user)
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            # Qualquer recurso que case com o padrão acima não terá seu acesso restrito.
            security: false # Liberação do acesso.
        main:
            lazy: true # Evita que a sessão seja criada enquanto ela não for necessária.
            # O firewall main usa o provider app_user_provider.
            provider: app_user_provider

            # Se o usuário não estiver autenticado neste firewall, é redirecionado pra login.
            form_login: 
                login_path: app_login # Rota do redirecionamento caso não autenticado.
                check_path: app_login # Rota de processamento da requisição POST do login.
 ```
O Symfony usa o conceito de firewalls. Os firewalls são acessados/separados por um padrão de RegEx (veja a configuração `firewalls:dev:pattern`).

Cada firewall tem um nome diferente e um provedor de usuário diferente (conforme seção `providers`). No exemplo, o `provider` do firewall `main` é o mesmo da entidade User que criamos (veja a seção `providers`, ela tem o `app_user_provider`). 

Os redirecionamentos voltados para autenticação e processamento do login são feitos conforme a seção `form_login` dentro do firewall (no nosso exemplo, o firewall `main`).

O `LoginController` terá o seguinte código:
```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function index(AuthenticationUtils $autehenticationUtils): Response
    {
        // Última mensagem de erro de autenticação.
        $error = $autehenticationUtils->getLastAuthenticationError();
        // Último usuário que tentou ser autenticado.
        $lastUsername = $autehenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
    ]);    }
}
```

No template Twig para login do usuário, os campos de usuário e senha **NECESSARIAMENTE** precisam ser `_username` e `_password`:
```HTML
{% extends 'base.html.twig' %}

{% block title %}Login{% endblock %}

{% block body %}
    {% if error %}
        <div class="alert alert-danger">{{ error.messageKey|trans(error.messageData, 'security') }}</div>
    {% endif %}

    <form action="{{ path('app_login') }}" method="post">
        <label for="username">Email:</label>
        <input type="text" id="username" name="_username" value="{{ last_username }}">

        <label for="password">Password:</label>
        <input type="password" id="password" name="_password">

        {# If you want to control the URL the user is redirected to on success
        <input type="hidden" name="_target_path" value="/account"> #}

        <button type="submit">Login</button>
    </form>
{% endblock %}
```
# Registrando usuários
Vamos usar um bundle externo ao Symfony desta vez!

```
composer require symfonycasts/verify-email-bundle

Info from https://repo.packagist.org: #StandWithUkraine
Using version ^1.13 for symfonycasts/verify-email-bundle
./composer.json has been updated
Running composer update symfonycasts/verify-email-bundle
Loading composer repositories with package information
Updating dependencies
Lock file operations: 1 install, 0 updates, 0 removals
  - Locking symfonycasts/verify-email-bundle (v1.13.0)
Writing lock file
Installing dependencies from lock file (including require-dev)
Package operations: 1 install, 0 updates, 0 removals
  - Downloading symfonycasts/verify-email-bundle (v1.13.0)
  - Installing symfonycasts/verify-email-bundle (v1.13.0): Extracting archive
Package sensio/framework-extra-bundle is abandoned, you should avoid using it. Use Symfony instead.
Generating optimized autoload files
109 packages you are using are looking for funding.
Use the `composer fund` command to find out more!

Symfony operations: 1 recipe (9a9131f6dd84f1dde6b65b8e9a84b1c3)
  - Configuring symfonycasts/verify-email-bundle (>=v1.13.0): From auto-generated recipe
Executing script cache:clear [OK]
Executing script assets:install public [OK]

 What's next? 


Some files have been created and/or updated to configure your new packages.
Please review, edit and commit them: these files are yours.
```

Depois que o comando acima é executado, o arquivo `config/bundles.php` é atualizado:
```php
<?php
return [
    // ... outros bundles instalados.
    SymfonyCasts\Bundle\VerifyEmail\SymfonyCastsVerifyEmailBundle::class => ['all' => true],
];
```

Os arquivos atualizados ao adicionar essa dependência são:
1. `composer.json`;
2. `composer.lock`;
3. `symfony.lock`;
4. o próprio arquivo `config/bundles.php`.

A dependência `SymfonyCastsVerifyEmailBundle` acrescenta à CLI do Symfony um comando novo para criar um formulário de criação de usuário:
```
php .\bin\console make:registration-form

 Creating a registration form for App\Entity\User

 Do you want to add a @UniqueEntity validation annotation on your User class to make sure duplicate accounts aren't created? (yes/no) [yes]:
 > yes

 Do you want to send an email to verify the user's email address after registration? (yes/no) [yes]:
 > no

 Do you want to automatically authenticate the user after registration? (yes/no) [yes]:
 >

 ! [NOTE] No Guard authenticators found - so your user      
 !        won't be automatically authenticated after        
 !        registering.                                      

 What route should the user be redirected to after registration?:
  [0 ] _preview_error
  [1 ] _wdt
  [2 ] _profiler_home
  [3 ] _profiler_search
  [4 ] _profiler_search_bar
  [5 ] _profiler_phpinfo
  [6 ] _profiler_xdebug
  [7 ] _profiler_search_results
  [8 ] _profiler_open_file
  [9 ] _profiler
  [10] _profiler_router
  [11] _profiler_exception
  [12] _profiler_exception_css
  [13] app_episodes
  [14] app_watch_episodes
  [15] app_login
  [16] app_seasons
  [17] app_series
  [18] app_series_form
  [19] app_add_series
  [20] app_delete_series
  [21] app_edit_series_form
  [22] app_store_series_changes
 > 17

 updated: src/Entity/User.php
 created: src/Form/RegistrationFormType.php
 created: src/Controller/RegistrationController.php
 created: templates/registration/register.html.twig

 
  Success! 
 

 Next:
 Make any changes you need to the form, controller & template.

 Then open your browser, go to "/register" and enjoy your new form!
```
Depois que o assistente de criação de formulário de cadastro de usuários é executado, os seguintes arquivos são criados/modificados:
1. updated: `src/Entity/User.php`:
```php
<?php

namespace App\Entity;

// ... outros imports...
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{ /*... resto do código... */ }
```
2. created: `src/Form/RegistrationFormType.php`:
```php
<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            /*
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You should agree to our terms.',
                    ]),
                ],
            ])
            */
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
```
3. created: `src/Controller/RegistrationController.php`:
```php
class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();
            // do anything else you need here, like send an email

            return $this->redirectToRoute('app_series');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
```
4. created: `templates/registration/register.html.twig`:
```HTML
{% extends 'base.html.twig' %}

{% block title %}Cadastrar usuário{% endblock %}

{% block body %}
    {{ form_start(registrationForm) }}
        {{ form_row(registrationForm.email) }}
        {{ form_row(registrationForm.plainPassword, {
            label: 'Senha'
        }) }}

        <button type="submit" class="btn btn-primary">Register</button>
    {{ form_end(registrationForm) }}
{% endblock %}
```
# Para saber mais: Bundle
O comando `make:registration-form` não é do bundle externo que instalamos. Ele já vem com o framework full-stack do Symfony e só depende do pacote symfonycasts/verify-email-bundle para realizar o processo de verificação de e-mail. Esse processo não foi mostrado nesse treinamento pois nós ainda não vimos como enviar e-mails, então algumas coisas ficariam confusas.

Mas esse bundle instalado, na verdade, não foi usado no último vídeo e pode inclusive ser removido. 

# Protegendo rotas
Configure o controle de acesso por meio da configuração `security:access_control` no arquivo `config\packages\security.yaml`:
```YAML
security:
    access_control:
        # Qualquer path diferente de login e register só é autorizado depois de logado.
        - { path: ^/(?!login|register), roles: ROLE_USER } 

```
Veja que a entidade de usuário garante que pelo menos o papel `ROLE_USER` existe para todos os usuários logados:
```php
/*... resto do código... */ 
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /*... resto do código... */ 

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Garante que todos os usuários tenham pelo menos o papel ROLE_USER.
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }
    /*... resto do código... */ 
}
```

# Buscando usuário
As informações do usuário podem ser acessadas no template Twig via objeto `app.user`:
```HTML
<!-- Resto do código -->
<body>
    <div class="container">
        <p>{{ app.user ? app.user.userIdentifier : "&lt;Anônimo&gt;" }}</p>
        <!-- Resto do código -->
    </div>
        <!-- Resto do código -->
</body>
```
Dentro do Controller, o usuário pode ser obtido por meio do método `$this->getUser()`:
```php
#[Route('/series', name: 'app_series', methods: ['GET'])]
public function index(Request $request): Response
{
    $seriesList =  $this->seriesRepository->findAll();

    return $this->render('series/index.html.twig', [
        'seriesList' => $seriesList,
        // Repassar o usuário para o template:
        'user' => $this->getUser(), 
    ]);
}
```

Saiba mais na documentação: https://symfony.com/doc/current/security.html#fetching-the-user-object

# Fazendo logout
O legal é que o logout não depende de um controller ou action separados! É muito simples: basta editar os arquivos de configuração `config\packages\security.yaml` e `config\routes.yaml`:

```YAML
# config\packages\security.yaml
security:
    firewalls:
        main:
            # Se o usuário não estiver autenticado neste firewall, é redirecionado pra login.
            form_login: 
                login_path: app_login # Rota do redirecionamento caso não autenticado.
                check_path: app_login # Rota de processamento da requisição POST do login.
                default_target_path: app_series # Rota padrão após realizar o login.
            logout:
                path: app_logout # Rota que vai realizar o logout.
                # A rota app_logout é definida no arquivo config/routes.yaml.
                target: app_login # Rota padrão após realizar o logout.
```
```YAML
# config\routes.yaml
app_logout:
    path: /logout
    methods: GET
```
