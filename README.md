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
