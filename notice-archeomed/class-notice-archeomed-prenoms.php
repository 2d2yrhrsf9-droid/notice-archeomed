<?php
/**
 * Les prénoms que l'on connaît, pour un avis sur celui qu'on a tapé.
 *
 * La liste réunit les prénoms donnés en France à mille enfants au moins
 * depuis 1900, et des prénoms étrangers d'usage courant : environ trois
 * mille cinq cents, en minuscules et sans accents. Elle ne juge jamais : un
 * prénom qu'elle ignore n'est pas faux, il est rare ou étranger (Katarzyna,
 * Iñaki, Wandrille), et l'on se tait. L'avis ne retient pas l'envoi.
 *
 * Quatre avis, et un seul par personne :
 *  - le prénom est un nom de famille fréquent, et le nom un prénom connu :
 *    les deux champs semblent échangés (« Durand », « Martin ») ;
 *  - le prénom est connu sans accent, et la liste ne le connaît qu'avec un
 *    seul (« Helene », « Hélène ») : on propose cette forme. Le tréma n'est
 *    jamais proposé — Mael, Loic, Anais sont des graphies d'usage — ni les
 *    formes étrangères qui s'écrivent sans accent (Andrea, Felix, Kevin) ;
 *  - une lettre tapée trois fois de suite (« Jeannne ») : aucun prénom de
 *    la liste n'en a, c'est une faute de frappe sûre. On propose la forme
 *    connue qu'elle cache, en doublant ou en simple ; à défaut, on le dit ;
 *  - deux lettres échangées (« Jaenne ») ou une touche voisine sur un
 *    clavier français (« Jeamne ») : si un seul prénom connu est à cette
 *    distance, on le propose. La première lettre n'est jamais en cause, ni
 *    un prénom de moins de quatre lettres, et une lettre de plus ou de moins
 *    ne se relève pas : elle fait souvent un autre prénom, rare et juste
 *    (Jehanne). Un prénom rare mais donné en France, à une faute d'un
 *    prénom connu (Jeanine, Maelle), est tenu pour juste : la liste RARES
 *    les réunit, tirée du fichier des prénoms de l'Insee (2025).
 *
 * Le navigateur reçoit les mêmes listes (pour_le_navigateur()) et applique
 * les mêmes règles : l'auteur lit en tapant ce que la rédaction lit ensuite.
 *
 * @package notice-archeomed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notice_Archeomed_Prenoms {

	/** Les lettres accentuées, et leur lettre nue, au même rang. */
	const ACCENTS = 'àáâäãåāăąçćčďđèéêëēėęěìíîïīįñńňòóôöõøōőùúûüūůűýÿžźżšśşťřłğ';
	const NUES    = 'aaaaaaaaacccddeeeeeeeeiiiiiinnnoooooooouuuuuuuyyzzzssstrlg';

	/** Les prénoms connus, en minuscules et sans accents. */
	const CONNUS = <<<'LISTE'
aaliyah aaron abby abd abdallah abdel abdelaziz abdelhakim abdelhamid abdelkader abdelkarim
abdelkrim abdellah abderrahmane abdou abdoul abdoulaye abdullah abel abigael abigaelle abigail
abou aboubacar aboubakar abraham aby achille achraf ada adam adama adame adan adel adelaide
adele adelie adelina adeline adem aden adil adnan adnane adolphe adolphine adonis adrian adriana
adriano adriel adrien adrienne aelys agathe aglae agnes ahlem ahmad ahmed ahmet aicha aida aidan
aiden aimable aime aimee aimie aimy aina ainhoa aisha aisling aissa aissata aissatou akim akram
aksel aksil alaa alain alan alana alaric alasdair alassane alba alban albane albanie alberic
albert alberta alberte albertine alberto albin albine alcide aldo aldric alec alejandro
alessandro alessia alessio alex alexa alexander alexandra alexandre alexandrine alexane alexi
alexia alexiane alexie alexina alexine alexis alexy aleyna alfred alfreda alfredo ali alia alice
alicia alida alienor aliette alima alina aline alisa alisha alison alissa alissia alisson
alistair alix alixe aliya aliyah alize alizee allain allan allison alma alois aloise alonzo
aloys aloyse alpha alphonse alphonsine alton alun alvin alwena aly alya alyah alycia alyson
alyssa alyssia alysson amadou amael amaia amal amalia amalya amand amanda amandine amani amar
amara amaury amaya amber ambre ambrine ambroise ambroisine amede amedee amel amelia amelie
ameline amelle amin amina aminata amine amir amira amjad amy ana anabelle anae anael anaelle
anais anas anass anastasia anastasie anatole anaya andre andrea andreas andree andrew andy anes
ange angel angela angele angelica angelina angeline angelique angelo angie ania anicet anick
anis aniss anissa anita ann anna annabel annabelle annaelle anne annelise annette annic annick
annie annik annita annonciade anny anouar anouchka anouck anouk anselme anthea anthime anthonin
anthony antoine antoinette anton antone antonella antoni antonia antonin antonine antonio antony
anya aoife apolline appoline april archibald archie arda aria ariana ariane ariel arielle aris
aristide arlene arlette armance armand armande armandine armando armel armelle arnaud arnauld
arnault arno arnold aron arsene arthur arthus artur arturo arwen arya ashley asma asmaa assa
assia assil assiya assya astrid astride asya athanase athena athenais aubin aubrey aude audrey
audric augusta auguste augustin augustine aurel aurele aurelia aurelie aurelien auriane aurora
aurore auxane auxence ava avril awa awen axel axelle aya ayah ayan ayana ayaz aydan ayden ayla
aylan aylin ayline ayman aymane aymen aymeric aymerick ayna ayoub ayrton ayse azad aziliz aziz
aziza azra azzedine bachir badis badr bakary balthazar baptiste baptistin baptistine barbara
barbe barnabe barry barthelemy basile basma bastian bastien batiste baudouin baya beatrice
beatrix becky belen belinda belkacem bella ben benedicte benjamin bennie benny benoist benoit
benoite berangere berat berenger berengere berenice bernadette bernard bernardine bernice bert
bertha berthe bertille bertin bertrand bessie beth bethany betsy bette bettie bettina betty
bettye beulah beverly beyza bianca bilal bilel bill billal billel billie billy binta bintou
blaise blanche blandine bleuenn bob bobbie bobby boniface bonita bonnie boris boubacar bouchra
brad bradley brahim brandi brandon brandy brayan brenda brendan brent bret brett briac brian
brice bridget brieuc brigitte brittany bronwen brooke bruce bruna brune bruno bryan burak byron
caleb cali calie calista calixte callie calvin calypso camelia cameron camil camila camilia
camilla camille candace candice candy cannelle capucine cara carine carinne carl carla carlo
carlos carmela carmelo carmen carol carolane carole carolina caroline carolyn carrie cary
casimir cassandra cassandre cassidy cassie cassiopee cassy castille cataleya catalina catherine
cathia cathie cathleen cathy cecelia cecil cecile cecilia cedric cedrick celeste celestin
celestine celia celian celie celina celine celya cendrine cerise cerys cesar cesarine chad
chadia chahid chahine chahinez chaima chakib chanel chantal chantale charlemagne charlene
charles charlette charley charli charlie charline charlize charlotte charly charlyne chayma
cheick cheikh chelsea chelsy cheri cherie cherif cherifa cherine cheryl chester cheyenne chiara
chirine chloe chris christa christel christele christelle christian christiane christianne
christie christina christine christoph christophe christopher christy chrystel chrystele
chrystelle ciara ciaran cindy cinthia claire clairette clara clarence clarisse claude claudette
claudia claudie claudine claudio claudius claudy clea clelia clemence clement clementine cleo
clifford clifton clint clinton clive cloe clotaire clothilde clotilde clovis clyde coleen
colette colin coline colleen collette colm colombe colyne come conception connie constance
constant constantin cora coralie coraline corentin corentine corey corine corinne cormac cory
cosette coumba courtney craig cristelle cristiano cristina crystal curtis cylia cyndie cynthia
cyprien cyprienne cyriaque cyrielle cyril cyrille cyrine czeslaw dafydd dahlia daisy dale dalia
dalil dalila dallas damian damien damon dan dana danae dania daniel daniela daniele daniella
danielle danny dante dany daoud daouda daphne daphnee daria darin darine dario darius darla
darlene darrel darrell darren darrin darryl daryl dave david davina davy dawn dawson dayan
dayana dean deanna debbie debora deborah debra declan dee delbert delia della delores deloris
delphin delphine demba dena denis denise deniz dennis denys denyse derek dermot derrick derya
desire desiree devin dewayne dewey diana diane dianna dianne didier diego dieter dilan dilara
dimitri dimitry dina dino diogo divine dixie djamal djamel djamila djena djeneba djenna djibril
djilali dolores domenico dominic dominick dominique domitille don donald donat donatien
donatienne donia donna donnie donovan dora doreen doria dorian doriane dorine doris dorothea
dorothee dorothy doryan doug douglas dounia driss duane duncan dustin dwayne dwight dylan earl
earnest eda eddie eddy eden edgar edgard edith edithe edmee edmond edmonde edmund edna edouard
eduardo edward edwige edwin eglantine eileen eilidh el ela elaia elaine elbert eldon elea eleana
eleanor eleanore elena eleonore elfriede eli elia elian eliana eliane elianne elias elie elies
eliette elif elijah elina eline elio eliot eliott elisa elisabeth elise elisee elissa eliza
elizabeth ella ellen ellie elliot elliott elly elmer eloan eloane elodie elody eloi eloise elona
elora elouan elouann elsa elsie elvina elvira elvire elvis ely elya elyana elyane elyas elyes
elyna elyne elyo elysa elyse elyssa elza ema emanuel emelie emeline emelyne emeric emerick emie
emil emile emilia emilie emilien emilienne emilio emily emine emir emma emmanuel emmanuella
emmanuelle emmeline emmie emmy emna emre emrys emy enael enea eneko enes enguerrand enis enoha
enola enora enrico enrique enza enzo eoin erell eren eric erica erick ericka erik erika erin
erine erma erna ernest ernestine ernst erwan erwann erwin eslem esma esmee esmeralda esperance
esra essie esteban estella estelle esther etan ethan ethann ethel etienne etiennette etta eugene
eugenia eugenie eula eulalie eunice euphrasie eusebe eustache eva evan evann evans evariste eve
eveline evelyn evelyne even evy ewan ewen ewenn eya eyden eymen ezechiel ezekiel ezio ezra
fabian fabien fabienne fabio fabiola fabrice fadi fadila fahim faith faiza fannie fanny fanta
fantine fany faouzi farah fares farid farida farouk fathi fatih fatiha fatima fatimata fatma
fatna fatou fatoumata faustin faustine faycal faye federico felicia felicie felicien felicienne
felicite felix ferdinand feriel fern fernand fernanda fernande fernando feryel ffion filipe
filippo fiona firdaws firmin flavia flavie flavien flavio fleur fleury flora flore florence
florent florentin florentine florian floriane florianne florie florimond florine floris fortune
fouad foued fouzia franc france franceline frances francesca francesco francette francia
franciane francine francis francisca francisco francisque franck francky franco francois
francoise frank frankie frantz franz fred freda freddie freddy frederic frederick frederik
frederique fredrick fredy frida frieda friedrich fulbert furkan gabin gabriel gabriela gabriele
gabriella gabrielle gaby gael gaelle gaetan gaetane gaia gail garance gareth garry gary gaspar
gaspard gaston gatien gauthier gautier gayle gaylord gene geneva genevieve geoffray geoffrey
geoffroy georg george georges georget georgette georgia georgina georgine geraint gerald
geraldine gerard geraud gerhard gerhild germain germaine gerome gertrude gervais gervaise gery
gethin ghislain ghislaine ghyslaine giani gianni gil gilbert gilberte gilda gildas gilette
gilles gillette gina ginette ginger gino giorgio giovani giovanna giovanni giovanny gisele
giselle gislaine gislhaine giulia giulian giuliana giuliano giulio giuseppe gladys glen glenda
glenn glenna gloria glyn godefroy gontran gonzague gonzalo goulven grace gracie gracieuse
gratien graziella greg gregoire gregory gretchen guadalupe guenael guenaelle guido guilaine
guilhem guillaume guillemette guiseppe guislaine gurvan gustave guy guylaine guylene gwen
gwenael gwenaelle gwendal gwendoline gwendolyn gwenllian gwenn gwennaelle gwenola gwladys habib
habiba haby hacene hadja hadrien hafid hafida hafsa hailey hajar hakan hakim hakima halim halima
hamed hamid hamidou hamish hamza hana hanae hanan hanane hania hanna hannah hans harmonie
harmony harold haron haroun harouna harriet harry hasan hasna hassan hassen hatem hatice hatim
hattie hava hawa hayat hayden hayet hazel heather hector hedi hedwige heidi helen helena helene
helio helios helmut heloise henri henria henrietta henriette henrique henry herbert herman
hermance hermann hermine herminie herve herveline hiba hicham hichem hidaya hilaire hilal hilda
hildegarde hina hind hippolyte hocine holly homer honore honorine hope horace hortense houcine
houda houria hubert huberte hugh hugo hugues huguette humbert huseyin huw hyacinthe iain ian
ianis iban ibrahim ibrahima ibtissam ibtissem ida idir idris idriss idrissa ieuan ifor ignace
igor ihsan ikram ilan ilana ilann ilayda ilham ilhame ilhan ilhem ilian iliana ilias ilies illan
illona ilona ilyan ilyana ilyane ilyas ilyass ilyes ilyess imad iman imane imany imen imene
imogene imran imrane ina inaya inayah india ines iness inez ingrid inna insaf ira irem irena
irene irenee irina iris irma isaac isabel isabella isabelle isaiah isaline isaure ishak ishaq
isidore isis islam islem ismael ismail isra israa issa issam ivan ivana ivy iyad iyed izia jack
jacki jackie jacky jaclyn jacob jacqueline jacquelyn jacques jacquy jad jade jaden jaime jalil
jamal jamel james jamie jamila jan jana jane janelle janet janette janice janick janie janina
janine janique janis janna jannah jannick jany jaouad jared jarod jasmin jasmine jason jassim
javier jawad jawed jay jayden jayne jayson jean jeanette jeanine jeanne jeannette jeannick
jeannie jeannine jeannot jeff jefferson jeffery jeffrey jehan jehanne jenifer jenna jennah
jennie jennifer jenny jennyfer jeoffrey jerald jeremi jeremiah jeremie jeremy jeri jermaine
jerome jeromine jerry jesse jessica jessie jessim jessy jesus jewel jibril jihane jill jim
jimmie jimmy jo joachim joakim joan joana joann joanna joanne joannes joanny joao joaquim
joaquin joceline jocelyn jocelyne jodi jodie jody joe joel joele joelle joey joffrey johan
johana johann johanna johanne johannes john johnathan johnnie johnny johny jolan jolene jon
jonah jonas jonathan jonathon joni jordan jordane jordi jordy jorge joris jose josee josef
josefa joseline joselyne joseph josepha josephe josephine josette joshua josian josiane josianne
josselin josseline josselyne josue josyane joud joy joyce juan juana juanita jude judicael
judith judy jules julia julian juliana juliane juliann julianna julianne julie julien julienne
juliette juline julio julius julyan june junior jurgen justin justine kaan kader kadiatou kadija
kadir kahil kahina kaina kais kalvin kamal kamel kamelia kameron kamil kamila kamilia kaoutar
kara karel karelle karen kari karim karima karin karina karine karl karla kassandra kassim kassy
kataleya katarina kate katell katharina katharine katherine kathia kathleen kathryn kathy katia
katie katrina katy kawtar kawthar kay kayden kayla kaylan kaylia kayliah kayna kayron kays keith
kelia kelian kelli kellie kelly kellya kelvin kelya kelyan ken kenan kendra kenji kenjy kenneth
kenny keny kenza kenzi kenzo kenzy kerem keren keri kerim kerri kerry kessy ketsia ketty kevin
keyla keylan keylia keziah khadidja khadija khaled khalid khalil khalis kheira kiara kieran
kilian killian killyan kilyan kim kimberley kimberly kingsley kirsten klara klaus kleber klervi
kris krista kristel kristell kristen kristi kristie kristin kristina kristine kristopher kristy
krystal kurt kyara kyle kylian kyliann kylie kyllian lachlan ladislas laeticia laetitia lahcen
lahna laia laila laina lalie laly lambert lamia lamine lamya lana lance lancelot landry lara
larbi larissa larry lassana latasha latifa latoya laura laurane lauranne laure laureen laurel
laureline lauren laurena laurence laurene laurent laurentine laurette lauriane laurianne laurie
laurine laury lauryn lauryne laverne lawrence laya layan layana layane layla layna lazare lea
leah leana leandre leandro leane leanna leanne leeloo leelou leeroy lehna leia leigh leila leina
lela leland lelia lena lenaic lenaig leni lenna lenny lenora leny lenzo leo leocadie leon leona
leonard leonardo leonce leone leonel leonide leonie leonne leonor leonore leontine leopold
leopoldine leroy lesley leslie lesly lester leticia levi levy lewis lexie leya leyla leyna lia
liam liana lidia lidwine liham lila lilas lili lilia lilian liliana liliane lilianne lilie lilio
lillian lillie lilly lilou lilwenn lily lilya lina linda lindsay lindsey line linette lino
lionel lionnel lior lisa lisandro lisandru lise lisette lisiane lison lissandro liv livia livio
liya liyah liyana liz liza lizea lizio lizzie loan loana loane loann loanne loetitia loevan
logan lohan lohann loic loick lois loise lola lolita lonnie lony loona lora lorelei loren lorena
lorene lorenza lorenzo loretta lorette lori loriane lorie lorik lorine loris lorna lorraine lory
lorys lotfi lothar lou louan louana louane louann louanne louay loubna louca loucas louie louis
louisa louise louisette louisiane louison louiza louka loukas louna lounes lounis loup lowell
lowen luan luana lubin luc luca lucas luce lucette lucia luciana luciano lucie lucien lucienne
lucile lucille lucinda lucy ludivine ludmila ludmilla ludovic ludwig luigi luis luisa luka lukas
luke lula luna lupe luther lya lyah lyam lyana lyanna lydia lydie lyes lyham lyla lyle lylia
lylian lyliane lylio lylou lyna lynda lyne lynette lynn lynne lysa lysandre lyse lysiane lyvia
lyvio mabel mable maceo maddie maddy madeleine madeline madina madison madjid mady madyson mae
mael maela maelan maelia maelie maeline maelis maeliss maelle maellys maely maelya maelyne
maelys maelyss maena maeva magali magalie magaly magdalena magdeleine maggie maggy maguy
mahamadou mahault mahaut mahdi mahe maher mahmoud maho maia maily mailys maimouna maina mairead
maissa maissane maite maiwen maiwenn majda majid malak malaurie malaury malcolm malcom malek
malia malicia malick malik malika mallaury mallory malo maloe malone malonn malorie malory malou
malvina malya mama mamadou mamie manal mandy manel manelle manoa manoah manoe manolo manon
mansour manuel manuela manuella manuelle mao mara maram marc marceau marcel marcelin marceline
marcella marcelle marcellin marcelline marcia marco marcus mareva margaret margarete margarita
margaux margie margo margot marguerite maria mariam mariama mariame marian mariana mariane
marianna marianne mariannick mariano marie marielle mariem marien mariette marika marilene
marilou marilyn marilyne marin marina marine marinette mario marion marise marius mariya
marjolaine marjorie marjory mark marla marlene marley marlon marlyse maroua marouane marsha
marta martha marthe martial martin martine marty marvin marvyn marwa marwan marwane mary marya
maryam maryann maryannick marylene marylin maryline marylise marylou maryne maryse maryvonne
massimo mateo matheo mathew mathias mathieu mathilda mathilde mathis mathurin mathys matias
matilda matilde matis matisse matt matteo mattheo matthew matthias matthieu matthis mattia
mattie mattis matys maud maude maurane maureen maurice mauricette maurine max maxance maxence
maxens maxim maxime maximilien maximilienne maximin maxine may maya mayar mayeul maylan maylie
mayline maylis maylon maylone mayra mayron mayssa mayssane medard meddy mederic medhi medhy
medina medine megan megane meghan mehdi mehdy mehmet meissa melaine melanie melany melba
melchior melek melia melih melina melinda meline melis melisa melisande melissa melissandre
mellina melodie melody melvin melvyn melya melyna melyne melyssa mendy mercedes meriam meriem
merle merlin merve merwan meryam meryem meryl messaoud messaouda mewen mia michael michaela
micheal michel michele micheline michelle mickael mickey miguel mikael mikail mike mila milan
milana milann mildred milena milene milhan milhane milla milo miloud milton mina mindy minnie
mira miral miran mireille mirella miriam misty miya moana modeste mohamed mohammad mohammed
mohand moira moise molly mona monia monica monique morad morgan morgane morgann mouhamadou
mouhamed mouhammad moulay mouloud mouna mounia mounir mourad moussa moustafa moustapha muguette
muhammad muhammed murat murdo muriel murielle mustafa mustapha mya myla mylan mylann mylene mylo
myra myriam myriame myriem myrna myron myrtille myrtle nabil nabila nacer nacera nacim nada
nadege nadia nadine nadir nael naelle nahel nahia nahil nahla nahyl naia nail naila naim naima
nais najat najib najwa nancy nans nao naomi naomie naomy naoual naoufel narcisse nasser nassera
nassim nassima nassira nastasia natacha natalia natalie natan natasha nateo nathael nathalie
nathan nathanael nathanaelle nathaniel natheo nawal nawel nawelle nawfel naya nayah nayel nayla
nazim neela neil neila nejma nelia nell nella nellie nelly nelson nelya neo nesrine nessa nessim
nestor nettie neyla niall niamh nicaise nicholas nichole nick nicola nicolas nicole nicolle
niels nigel nihel nikita nikki nikola nil nils nina nino ninon nisa nisrine nizar noa noah noam
noan noe noel noela noele noelia noelie noeline noella noelle noellie noemi noemie noemy noha
noham nohan nohlan nola nolan nolann nolhan nolwen nolwenn noor nora norah norbert nordine noria
norma norman nour noura nourdine noureddine nouria numa nuno oceane oceanne octave octavia
octavie odette odile odille oihan oisin olfa olga olive oliver olivia olivier ollie ollivier
olympe omar ombeline omer ondine opal ophelia ophelie ora orane oriana oriane orianne orla
orlando orlane ornella orso orville oscar osman othman othmane othon otis otto oualid ouarda
oumar oumayma oumou ousmane oussama owain owen ozan pablo paco pacome padraig palmyre paloma pam
pamela paola paolo paquerette pascal pascale pascaline pat patrice patricia patrick patsy patti
patty paul paula paule paulette paulin pauline paulo pearl pedro peggy pelagie penelope penny
percy perle perrine pete peter petra petronille pharell philemon philibert philip philipp
philippe philippine phillip philomene phyllis pia piero pierre pierrette pierric pierrick
pierrine pierrot pietro pilar pio placide pol polly prescilia prescilla prescillia preston priam
prince prisca priscilia priscilla priscille priscillia prosper prudence prune qassim quentin
quitterie rabah rabia rachael rachel rachelle rachid rachida radia radouane rafael rafik rahim
rahma raissa raja ralph ramata ramazan rami ramon ramona ramy ramzi randal randall randy rania
ranya raoul raphael raphaele raphaelle raquel raul rayan rayane rayen rayhan rayhana raymond
raymonde raynald razane reba rebecca rebekah reda redouane regina reginald regine regis reine
rejane remi remy rena renald renan renato renaud rene renee renelle rex reynald rhiannon rhonda
rhys riad ricardo richard rick rickey ricky rida rigobert rihanna riley rim rina rita riyad
robbie robert roberta roberte roberto robin robinson robyn roch rochelle roderick rodney
rodolphe rodrigo rodrigue roger rohan roisin roland rolande rolland rollande romain romaine
roman romane romaric romeo romie romuald romy ron ronald ronan ronda ronnie rony roosevelt rosa
rosaire rosalia rosalie rosalind rosario rose roseline roselyne rosemarie rosemary rosemonde
rosetta rosette rosie rosine rosita rosy roxane roxanne rozenn ruairi ruben ruby ruddy rudolph
rudy rufus rui ruth ryad ryan rym saad sabah sabine sabri sabrina sabrine sacha sadia sadie
sadio safa safaa safia safiya safwan sahra said saida saint sainte saja sajid sakina salah salem
saliha salim salima salimata sallie sally salma salman salome salomon saloua salvador salvatore
salwa sam samantha samba sami samia samir samira sammie sammy samson samuel samy samya sana
sanaa sanae sandie sandra sandrina sandrine sandro sandy sania santa santiago saoirse sara sarah
sarra sasha satine saturnin saul sauveur savannah scarlett scholastique scott seamus sean
sebastian sebastien segolene sekou selen selena selene selim selin selma selyan semih sena
sephora seraphin seraphine serena serge sergine sergio serine serkan servane seth sevan severin
severine seydou shaima shaina shana shane shanice shanna shannon shanon shari sharon shaun shawn
shawna shayma shayna sheila shelby shelia shelley shelly sherazade sheri sherine sherri sherrie
sherry sheryl sheryne shirel shirley siana sibel sibylle sidi sidney sidonie sidra siegfried
sienna sigrid siham sihame sihem sila silas silvain silvana silvere silvia silvio simeon simon
simone simonne sinan sindy sinead siobhan sira sirine sixtine slimane sloan sloane smail smain
soan soane soann soazig soen sofia sofian sofiane sofien sohan sohane sohann soizic solal solan
solange solen solene solenn solenne soline solveig sondra sonia sonja sonny sony sonya sophia
sophian sophiane sophie soraya soren sosthene souad soufiane souhayl soukaina soulayman
souleyman souleymane souleymen soumaya soumeya soumia stacey stacie stacy stan stanislas
stanislawa stanley stecy steeve steeven stefan stefanie stefano steffy stella stephan stephane
stephanie stephen sterenn stessy steve steven stevens sue suleyman sullivan sully sullyvan
sulyvan susan susanne susie suzanne suzelle suzette suzie suzon suzy sven swan swann syana
sybille sydney sylia sylvain sylvaine sylvana sylvanie sylvere sylvestre sylvette sylvia
sylviane sylvianne sylvie sylvine sylvio syrine tabitha taha tahar taina tais talia talya tamara
tami tammie tammy tanguy tania tanya tao tara tarek tarik tasha tasnim tasnime tatiana tayeb
taylor tayron tea ted teddy telio teo terence teresa teri terrance terrence terri terry tesnim
tesnime tess tessa thais thalia thalya thao thea thelio thelma theo theodor theodora theodore
theophane theophile theotime theresa therese thereze thi thiago thibaud thibault thibaut thierry
thiery thimeo thomas thymeo tia tiago tiana tidiane tifany tifenn tiffanie tiffany tilio tim
timael timeo timmy timote timothe timothee timothy tina tino tiphaine tiphanie titouan tiziano
tobias toby todd tom tomas tommie tommy tomy toni tonia tony tonya toufik toussaint toussainte
tracey traci tracie tracy travis trevor tricia trina trisha tristan troy trudy trystan tudur tya
tyana tyler tylian tylio tymeo typhaine typhanie tyron tyrone ugo ulrich ulysse umut urbain
ursula ursule vadim valentin valentina valentine valentino valere valerian valerie valery van
vanessa vanille vanina velma vera verna vernon veronica veronique vianney vicente vicki vickie
vicky victoire victor victoria victorien victorin victorine viktor vincent vincente vincenzo
vinciane viola violaine violet violette virgil virgile virginia virginie vital vivian viviane
vivianne vivien vivienne vladimir von wael wafa wail walid walter wanda warda warren wassil
wassila wassim wayne wendell wendy werner wesley whitney wilbert wilbur wilfred wilfrid wilfried
wilhelm willard willem william williams willie willow willy wilma wilson winifred wissal wissam
wissem wolfgang wyatt xavier xaviere yacine yacoub yael yaelle yahia yahya yamina yamine yan
yani yanick yanis yaniss yann yannick yannis yara yasin yasmin yasmina yasmine yasser yassin
yassine yassir yaya yazid yelena ylan ylann yliana ylies ylona yoan yoann yohan yohann yolaine
yolanda yolande yolene yona yoni youcef youenn youmna youna younes youness youri yousra youssef
youssouf youssra ysaline ysee yuna yunus yusuf yvan yvann yveline yvelise yves yvette yvon
yvonne yvonnick zacharie zachary zack zahia zahra zaina zakaria zakariya zakarya zakia zara
zayan zayd zayn zaynab zayneb zehra zelia zelie zeyneb zeynep zia ziad zina zineb zinedine ziyad
zoe zohra zola zora zoubida zyad
LISTE;

	/**
	 * Des prénoms qui sont aussi des noms de lieu ou de famille : connus
	 * comme prénoms, ils ne font pas croire, dans le champ du nom, à un
	 * prénom et un nom échangés (« Fleury », « Lorraine »).
	 */
	const ECARTES = <<<'LISTE'
carolina chelsea el fleury france georgia jordan lorraine maya milan nancy nil orlando prince
roman saint sainte santa sidney sony van victoire virginia von willow
LISTE;

	/**
	 * Des noms de famille fréquents qui ne sont pas des prénoms, les mots
	 * d'un nom composé joints par « _ » : tapés comme prénom, ils font
	 * croire aux deux champs échangés.
	 */
	const NOMS = <<<'LISTE'
aubert aubry bailly barbier baron barre baudry benard berger besson bihan blanc blanchard bonnet
boucher bouchet boulanger bourdon bourgeois bouvet bouvier boyer breton briand brun brunet
carlier caron carpentier carre charpentier chauvet chauvin chevalier chevallier collet collin
cordier cousin da_silva delaunay delmas deschamps devaux dubois dubreuil dufour dumas dumont
dupont dupuis dupuy durand durant duval fabre faure fernandez ferrand ferreira fontaine fournier
gaillard garcia garnier gay gillet girard giraud gonzalez grondin gros guerin guichard guillot
guillou guyot hamon hebert hoarau huet jacquet jaouen joly kerboul klein lacroix laine lamy
langlois laporte laurens le_bihan le_borgne le_corre le_floch le_gac le_gall le_goff le_moal
le_roux leblanc lebon lebreton lebrun leclerc leclercq lecomte leduc lefebvre lefevre leger
legrand lejeune lemaire lemaitre lemoine leroi leroux leveque lopez maillard mallet marchal
marchand marechal martinez masse masson menard mercier meunier meyer michaud millet monnier
moreau morel moreno morin morvan moulin muller navarro paris pasquier payet pelletier perez
perret perrier perrin perrot petit picard pichon poirier pons poulain prevost renard renault rey
reynaud riou riviere roche rodriguez rossi rousseau roussel roux roy royer sanchez schmitt
schneider teixeira tessier vallet vasseur vidal weber
LISTE;

	/**
	 * Des prénoms donnés en France, plus rares, que l'on ne prend pas pour
	 * une faute de frappe du prénom connu dont ils sont voisins : tous ceux
	 * du fichier de l'Insee qui sont à une lettre échangée ou à une touche
	 * voisine d'un prénom connu, et quelques prénoms étrangers dans le même
	 * cas (Petr, Pere, Mercè).
	 */
	const RARES = <<<'LISTE'
aallyah aalyiah aanya aara aaya abas abass abdelazis abdelhalim abderrhamane abdillah abes abla
abril achley acil acya adaam adah adams adao adda adeel adei adelle ademe adhan adhane adia
adian adiana adiel adim adis adiza adjan adlene adna adon adonia adonie adra adriaan adriani
adrina adya adyan aelya aelye aelyna aeon aesa aewen aeya afan afia afnan afnane afraham agan
agana agnel agnela ahana ahlan ahmar ahmes ahna ahya aian aiana aiba aidah aifa aila ailan ailee
ailie ailsa aily aima aimed ainoha aira airelle aislinn aiya aiza aizen ajan ajana ajla ajlin
ajna akan akana akcel akcil akia akil akima akina akio aksha aksim alai alaia alani alara alaya
alaz albans alce alda alden aldin alecia alehandro aleix ales alesia alev alexandar alexei
alexian alexien alexsandro alfa alhan alhem aliaa alian alias alic alica alicja alifa aliha
alija alijah alim alin alis alise alisea alisee alisia alisja alisya alita alixia aliyan aliz
aliza alizon alkan alkis alla allma allya allyah almee alna aloha aloma alona alone alonso altin
aluna alva alvanie alvine alyan alyha alyia alyiah alys alysea alysia alyxia amad amada amae
amale amalys aman amana amane amans amanza amao amelien amelis amelja ameo amicia amid amida
amie amisha amissa amit amiya amla amma amni amon amor amora amra amri amsa amycia amyssia anaa
anaelie anai anaia anaid anaiz anar anase anastacia anastazia anay anays andde ander andres
andrra anea anel anfel anfele angeli angelien angelise angell angels anges angy anha anic anie
anifa anine anisa anise aniya anja anka annela annelies anneline annis anoa anoushka ansa ansel
ansi anta ante antonie antonu anyelo anys aona apdil aqsa aram arame aran arcene ardian ardiana
arel aren arfa arie ariette arin ariz arkam arkane arkene arla armanc armane arsa arsen arta
arthis arys asad asan asel aseline asen asha ashraf asil asja asra assim assita asta aswa atlan
atman atmane atmen aton aubery auda audelia audelie auden aure aureline aurella aurelle aurlane
avan avel avelina aveline aven avigael avigaelle avigail avraham aweb awel axele axeline axil
ayaa ayaan ayad ayae ayas ayat aycan ayce ayha ayia aykan aylah aylane aylem aylen aylon aylone
ayma aymeris aynaa aynes ayra ayran aysen ayvan ayven ayza ayzan ayzen azae azam azan azar azel
azele azelie azelina azeline azem azia azid azilis azis azriel badia badie baha balthasar baris
bashir bazile beatris beila belal belma bengy beniamin benjy benyamin berfin berrin berta berte
bertie bertina berton bertrane berty bethy bety beyda bilell bili blaize blanca bouchta boushra
brajan brando brayn briag briec brieux brigithe brithany bryce cael caela cagla calen calev
caliste calixta cami camie candide carma carmo casey cassis cattia catty caty cedrix celien
celiha cellia cezar cezarine chadli chafia chahines chahir chaida chaila chaira charki charlee
charleen charlet charlise charliz charlye charmette charmine chayam chazia chedi chedine chelcy
chemsy cherly chjara chleo christain christiaen christien ciarah ciline cira cirine clarie
claudien cleia clem cleya clhoe cody cole colene colins corry cozette cristian cyprine czeslas
daan daba dabia daby dael dafina dagan daha dahia dahila daila daina dainna dajana dalhia dalis
dalla dallia dame damia damjan dang danh danis danka danye dareen daril daris darko darly daryo
davis davud dayaan dayah daylia dayna deborha dehra delal denia denice deniss denize denya
denyce denyss devon devora dhalia diaba diabe diamila dian dianga diba didie diena dieneba diman
dinaa dinna dion djalal djalel djalila djana djanna djeneva doga doina dolan dominico dona donan
dorene doriand dorien dorina dorka dorlan dorlane dorone duena dwyane dyane eather eban eben
edan edann edeline edelyne edin edine editte edma edmone edvard edwan edwig edwine efna egan
ehan ehden ehtan eila eilan eily eiya eiza ekin elaa elae elain elaina elais elaka elanie elay
elayna elda eldana eldin eldina eleha eleis elfi elga elha eliab eliad eliah eliaz elida elide
elidie elien eliesa eliez eliha elija elijha elim elin elinah elior elioth elira elis elisca
elisea eliss elisse eliyah eliz elize elizee elke ellan ellana elle elli ellioth ellis elma
elmie elna eloa eloah eloana eloanne eloe eloen eloha elohan elohann eloize elone elorie elory
elouna eloy elsia elson eluan eluna elvia elvie elyad elyan elyasa elyaz elyesa elyha elys
elysea elyza elyze eman emee emelle emeris emia emiline emille emin emina emra emya enae enao
enda enee eneo enez enia enid enoah enoal enrick enso enzi eona epona eran erhan erico erij
erind erio eris erisa erla eron ersa ersan ersin erza esaie esen esna estevan ethem etna ettan
evah evahn evana evane evanh exan eyan eyla eylen eyven fadima fadl fahil fahima fahmi faid
faida faisa fanya faousi faran fardi fared farez farha fariha farila farima faris fariza farna
fatah fatha fathia fatiah fatila fatim fatin fatina fatiya fatoma fatuma fatumata favian
favienne favio fayim fayna faysal fayz fazia fazila felixia ferda ferdy filbert fiora fiore
fiorine flavine flona floraine floria florien florina florus fohad fousia francie francka frane
frans fransisco frederico fredj gais gaja gala gali gaspare gavin gavriel germanie gert ghilaine
ghizlaine ghylaine gian giano giovano gishlaine gismaine gizele gizelle gleb glodia glwadys
goeffrey golbert golda gradie grasiella gregorie grey guevan guilia guilian guiliana guiliane
guiliano guilio guislain guyliane guyslaine gwenhaelle gwenval habid habida hada hadel hadia
hadry hadya hafca hafif hafiz hafiza hailye haina haja hajra hakila halan haled halid halil
halley halmi hamda hamdi hamim hamir hamis hamsa hanad hanah hanis hannae hannan hany hanz hardy
hassna havid havida hayem hazem helois hemen hendy herline hermande hevi hichma hifa hilarie
hisham hishem hiva hnia hodaya hortence houra housine hughes hughette hulda iara ibragim idaline
idan idem idene idia ihlan ihlem ilaan ilahn ilaina ilane ilayna ilen ilene ilhana ilhane ilina
iliona ilisa iljana ilkan illana illina ilman ilna iloan iloha ilyaas ilyad ilyah ilyam ilyase
ilyaz ilyese ilyna imae imana imann imna imra imrah inais inan inata inayat inayha inea ineia
inesa inga inha insia insya ioan ioana ioane ioann ioen iqra iram iria irie irman irmane irsa
isaam isea ishai ishal ishan isia isie issac issao ivaan ivah iyas iyes izaac izabel izabella
izabelle izai izaiah izaline izhak izhaq izis izra jabier jace jacen jacquemine jadd jadon jaha
jahden jahe jahelle jahina jahis jahna jalal jalel jalila jamil janelie jania janic jannat
jannie janos jaren jasin jaslyn jaume jaxon jaydon jayen jayzon jaze jazmine jazon jeaninne jeef
jehna jena jenan jenane jenine jennat jeyan jhana jhoan jhon jhonathan jhonny jhony jian jiana
jianny jihan jihanne jilan jilian jiliane jilyan jinane joab joah joahn joam jocleyn joddy joee
joeffrey joelie joffret johanan johane johna johnattan joleen jona jonan jonaz jonhy jonn
jonnathan jonny jordans jori joric jorry jory joscelin josceline joscelyne joseba joset josiah
josie josquin joss josse josuan josuha josy jouan jouanna joye joze jozef jozefa jozette joziane
judd juge julina juluan julyna juna jury jyan jylian kabil kada kadel kadi kadim kadjatou kadri
kael kaela kafia kahim kahlan kahlil kaia kaira kalel kalil kalila kalya kalyan kamilla kamla
kana kanya kaona karell karem karia karil karma kasey kasija katel katelle kathlene kati katina
katja katlheen kattia katty kaya kayal kayan kaydon kayil kayila kayina kaylah kaylian kaylis
kaylla kayllah kayra kaysen kayton kayven kayz kayzen keann keden kehan kehlia keila keilan
keily keilya keiran keis keity keiyan kelhan keliah kelina kelis keliya kella kellan kellys
keloan kelyah kelyna kelys kenai kenay kenda kendi kendy kengy kenna kenni kensi kensy kent
kesiah kethy keti keylah keylis keylla keymia keyn keysia kezian khadidia khadiya khail khaina
khalia khalie khalif khamis kheria khiera kiana kilina killan kirsty klelya kleya kolyan krisha
kristian kyana kyllan kymie ladislaw laena lahra lahsen laian laiba laika laim laima laira lais
lala lalia lalla lalya lamis lamys lanha lania lanna laona lars latufa lauane laude laudine
laugane laula launa lauraine laurens laurenz laurien lauris layaan layah layal layba layha layia
layka layl laynna layra lazard leahna leahne lean leander leaticia leatitia lebna leely leha
lehny leian leika leine leis leith lejla lella lelya lemia lenae lenais lenha lenhy leno lenoe
lens lenya lenz leobard leoh leonice leonid leonis leonise leovan leslye letisia leyal leyan
leyia leyn leyns leys lhana lhena lhiam lhyam liahm lian liane liann lianne lias liba licia
liciana liciano licio lida lidiane lidio lidivine liee lien liena lies ligia liha lihan lihana
liia lijana lika lilae liliah lilja lilla lillo lilo liloa liloe liloo lilu lima linaa linea
link lion liona lira liria lisea lisia lisio lisna lita litana livai liwen lixia liyam liyan
lizae lizandro lize lizette lizia liziane lizon llana llona lluis lluna llyah llyana loah loam
loar loda lodia loen loena loene loenie loey logann loham lohana lohane lohanne lohis loica
loice loid loide loika loina loiss loiz loize loli lolia lolie lolly loly lolya loma lona lonan
long loraine loran loreen lorelie lorenda lorens lorenso lorenz loria loric lorien lorriane lors
lorya loryc lorye losa louange louba louei louhna louiss louize louizon loula lounea lounia
lounna louva lovan lovia loya loyan loyana loyann loyna lozea luam luanna luara luba lucaz
lucine lucja lucka ludie ludovine ludwin ludwine ludy luixa luiz luiza lukaz luli lulia lulian
luliana luma lunaa lune lusia luvia luvio luyana lyaa lyaan lyahm lyahna lyan lyanha lyas lyat
lycia lyciana lyciano lycio lyda lydiane lydivine lyed lyel lyena lyha lyia lyiah lyiana lyliah
lylla lyloo lyma lynea lynh lyria lysia lysie lyvai lyvie lyza lyze lyzia lyziane lyzie mabelle
macine macy mada madeo madhi madian madiana madiane madiba madie madiha madilyn madilyne madin
madine madiya madlie madline madly madlyne madona madyline maedy maeh maeley maelic maelien
maelise maeliz maella maellya maellye maelon maelone maelye maelyse maesha maevin maewa maewan
magdi magdy magy maha mahar mahdy mahed mahee mahel mahelle mahi mahid mahlon mahlone mahon
mahrane mahreen maian maid maida maide maiha maija maiky maila mailya mailye maira mairam
mairame maire mairon maiya maja majd majdi maje maka mala malal malau male malea maleo maley
maleya malha mali malicka malie malio malka malkia malle mally maloa maloh malohe malte malwen
malwenn malys mamia mamou manali manao manda mandi mane manea manek maneo mano manoha manoli
maoh maona maral marat marcellien marck marga margaud mari mariah marica mariel marig mariia
marija marik marila marino marita marize marja marjan marjana marjane marjanna marko markus
marleen marlen marlette marlie marline marlo marly marlyne marlys marni marnia marnie maro maroa
maroi maroia maroin marry mars marsel marso martins marton martta marvel marwah marx maryo
maryon marys maryze mata matar mate mateho matei matel mathei mathia mathie mathiss mathya matia
matin matina matine matio matiss matiya mattei mattew matthia mattias mattieu mattin matty
mattys maty matya maur maura maure maurene maurise mauve mavel maveline mavy maxene maxense maxy
mayda maydon maye mayer mayla maylah maylia maylin mayram mayreen mayrine mays maysaa mayton
mayz mazna meana mediba mediha medni meggan meghna meila meivyn meiya mejdi meldin melha melian
meliane melica melida melie meliha melin meliss meliza melona melos melua melyan melyha melys
mency mendi merce merima merina merine merjem meruem mevan meyan meydi meyla meyram meyrem mhedi
mhedy mialy mian miana miassa michela miguette miha miia mija mika milaan milah mile mili milia
milka milly milyan miora mirab mirah miraj mirielle mirna mitsy moan moha mohrad moina molene
molla mondy moran morvan mounis mouraz moya muad muguel mulan muna myassa myia mylah myli myran
myriel myrielle naaim nabel nabia nabyl nacia nacio nader nadera nadie nadim nadja naelie naem
nafila nagia nagla nahei nahila nahim nahis nahtan nahym naid naida naik naika naimi naja najah
najia najih najla najm najma nakib nala nalha nali nalia nalla nalya nami namia nana nandy nane
naoh naoial naola naolie naoly naoma naos nara naria naser nasera nasia nasim nasir nassef
nassila nassita nastasja nastazia nata natia natnael nattan natteo nava nayal nayan nayem nayha
nayhan nayil nayka nayma nays naza nazia nazir nazmi neal neels neema negla nehla neika neils
neily neim neima neiya nejla nelcon neli nelma nelo neola neoline neomie neyma nhael nhoa nholan
niah niama nibel nica nico nicolae nida niel niele niha niham nihan nila nilan nilann nile nima
nimah ninel nini ninno ninog nira nirma nisar noahm noahn noami noar noeli noelien noely noem
noema noemia nohra noia noira nolah nolahn nolanh nolyan noman nona nono nooa noran norcine
norha noris norlan norsine nouar nourcine noursine noya noyan nuna nyno ohian oifa okhan olaf
olicia olta olya omra oria oris orlana orland orlanne ornelia ottman ottmane otton oumra
patrisia paulien pavlo pere petar petr pressilia rabab rabat rabha rabya radja rafah rafia rahan
rahia rahil rahyan raia raid raida raim raina rajan rali raly ramadan ramina ramiz ramsi rana
ranim rashel rashid rathana raya rayah rayban rayd rayhaan rayim raym rayma raymone rayn rayna
raynale rayne rayyan rayyana razia read rean regan regia rehan rehane rejan remo reva reyane
reza rhama rhianna rian riaz rich richelle rico rihad rihan rija rijad riman rimane rinda riva
riya riyanna riyas riza roaa roann roby rock rocky roda rodine rody rogee rojan rojda rolan
romains romanie romel rona ronin rosaline rosane rosanne rosenn ross rouan roxy royan roza
rozalia rozalie roze rozie rozy ruan ryaz ryda ryiad ryna saana saba sabaa saban sabha sabia
sabien sabir sabra sacia sada sadi sadik sadim sadiq sadiya sadja sadjo sadra safah safiha
safine safyia saga sagia sagra sahar sahid sahina sahna sahyna saif saifa saim saima saina saira
sajaa sajda sajia salam salha salhia sali salia salime salina salira saliya salla salmah salmi
saloa saloia saloy saly salya samah samid samie samiel samiha samil samna sanad sanaf sanah
sanai sancie sandor sandri sandu sanja sanka sansa santiano sanya saran saray sarha saria sarine
sariya sarta sata satia sava savia savine savio saya sayma sayna sayra scoty seba seban sedan
sedine sefkan sega selio selyna semin serfe serigne serrine servine sety sevag seylan shaan
shadi shafi shaila shain shan shania shanise shanley shanonn shanya shaona shara shauna shayan
shayla shayn sheily sheima shella shellsy sherly sherone shurley shyana sian siane siar sidar
sidgey sidjey sido sidoine siera sifa sifra siga sihan sihane sika silan silene siline silly
sima simine sinaa sinam sinem sinna sirra sita siyam siyem soah soahn soana soanh soban sobia
sofine sogan sogane sohad soham soifa soina soisic sola solam solane solanne soleen solune sona
sonay sonna sora soria sorian soriane sorine souan souane soupaya soyan soyane soyann staecy
stana stane stanislaw steban stedy steev stefani stefen stefy stelia stelly stesy stevan stevano
stevee steveen stevene stevy suelly sundy sura susy suzan syan syane syanna syldia syldie sylla
sylvester sylvian sylvins taar tadek tahia tahiana tahis tahlia tahlya taho tahra tahya taia
taian taiba taiga taila taim talha tali talis talla talys tamia tamime tamya tana tanis tanja
tanys taos tata tatjana taya tayla tayna tedi teha teho teilo teina tereza thai thaila thalys
thana thayla thereza thibaux tian tiano tibo tidjane tiffaine tiga tiha tileo timoe timy tiny
tobia tomaz tomeo tona tong tory tricha tuana tulio tuna tyleo tyloan tymoe umit vahina veda
veena vega veran victorie vidian vidiane vincenso wali walis wassima wilfride wilheim willa
williame willis wissel xabier yaasir yacin yaelie yafa yahn yahnis yalina yaline yang yanhis
yanic yanise yannic yannie yaqin yasid yasine yasline yassri yazin yazmina yban ydan ydann yina
ylang ylhan ylhann ylina yllana yloan yman ynais yoah yoahn yogan yohenn yohna yoona youan youba
youga younese youns younse younss yousef ysra yuan yuba yunis yusif yzaline yzee zahar zaian
zaid zaiha zakaryia zalia zarha zayah zayna zaynah zayr zayra zays zemia zeynel zhara zhora zian
ziana zida ziha zihad zira zohar zoia zorha zoura zyan
LISTE;

	/** Les rangées d'un clavier français, pour les touches voisines. */
	const CLAVIER = 'azertyuiop qsdfghjklm wxcvbn';

	/** La seule forme accentuée que la liste connaisse à ces prénoms. */
	const ACCENTUES = <<<'LISTE'
adèle adélie aimé aimée alizée aliénor amélie anaé andré andrée angèle angéline angélique arsène
aurèle aurélia aurélie aurélien béatrice bénédicte bérangère bérengère bérénice camélia
cassiopée charlène christèle cléa clémence clément clémentine cécile cédric célestin célestine
célian céline célya césar côme daphné dorothée désiré eléa eléana eléanore eléonore esmée eugène
eugénie françois françoise frédéric frédérique félicie geneviève gisèle grégoire géraldine
gérard hanaé hervé hélène imène josué josé joséphine jérémie jérôme kamélia kélia kélya laurène
lizéa léana léandre léane léanna léanne lény léonard léone léontine léopold mahé maloé manoé
marlène marylène mathéo matthéo mattéo miléna mylène mégane mélanie mélia mélina méline mélodie
mélya mélyna mélyne nadège nathéo natéo noé noélie noéline noémie nélia nélya océane ophélie
pacôme philomène pénélope rené régine régis rémi rémy salomé siméon solène stéphane stéphanie
sébastien ségolène sélène séléna séréna séverine thiméo thyméo thélio théodore théophile thérèse
timothé timothée timoté timéo tyméo télio valérie véronique ysée zélie
LISTE;

	/** Les listes, lues une fois. */
	private static $listes = null;

	/** Les listes en tables, la clé étant le prénom sans accents. */
	private static function listes() {
		if ( null === self::$listes ) {
			$table = function ( $texte ) {
				$l = preg_split( '/\s+/', trim( $texte ) );
				return array_fill_keys( $l, true );
			};
			$accentues = array();
			foreach ( preg_split( '/\s+/', trim( self::ACCENTUES ) ) as $forme ) {
				$accentues[ self::cle( $forme ) ] = $forme;
			}
			self::$listes = array(
				'connus'    => $table( self::CONNUS ),
				'ecartes'   => $table( self::ECARTES ),
				'noms'      => $table( self::NOMS ),
				'rares'     => $table( self::RARES ),
				'accentues' => $accentues,
				'voisines'  => self::voisines(),
			);
		}
		return self::$listes;
	}

	/**
	 * Les touches voisines de chaque lettre : à côté sur la même rangée, ou
	 * sur la rangée du dessus ou du dessous, à une colonne au plus.
	 */
	private static function voisines() {
		$rangees  = explode( ' ', self::CLAVIER );
		$voisines = array();
		foreach ( $rangees as $r => $rangee ) {
			for ( $i = 0; $i < strlen( $rangee ); $i++ ) {
				$v = '';
				foreach ( $rangees as $r2 => $autre ) {
					for ( $j = 0; $j < strlen( $autre ); $j++ ) {
						if ( ( $r2 === $r && abs( $j - $i ) === 1 ) || ( abs( $r2 - $r ) === 1 && abs( $j - $i ) <= 1 ) ) {
							$v .= $autre[ $j ];
						}
					}
				}
				$voisines[ $rangee[ $i ] ] = $v;
			}
		}
		return $voisines;
	}

	/** La longueur d'un prénom en deçà de laquelle on ne relève pas de faute de frappe. */
	const COURT = 4;

	/**
	 * Le prénom connu, et le seul, dont une partie inconnue est à deux lettres
	 * échangées ou à une touche voisine, la première lettre gardée ; ou null.
	 * La partie est une clé, en minuscules et sans accents ; ses $debut
	 * premiers signes viennent de la première lettre (deux pour « Œ »).
	 */
	private static function voisin( $c, $debut ) {
		$l = self::listes();
		if ( strlen( $c ) < self::COURT || ! preg_match( '/^[a-z]+$/', $c ) || isset( $l['rares'][ $c ] ) ) {
			return null;
		}
		$trouves = array();
		$n       = strlen( $c );
		for ( $i = max( 1, $debut ); $i < $n; $i++ ) {
			if ( $i < $n - 1 && $c[ $i ] !== $c[ $i + 1 ] ) {
				$essai = substr( $c, 0, $i ) . $c[ $i + 1 ] . $c[ $i ] . substr( $c, $i + 2 );
				if ( isset( $l['connus'][ $essai ] ) ) {
					$trouves[ $essai ] = true;
				}
			}
			foreach ( str_split( $l['voisines'][ $c[ $i ] ] ) as $d ) {
				$essai = substr( $c, 0, $i ) . $d . substr( $c, $i + 1 );
				if ( isset( $l['connus'][ $essai ] ) ) {
					$trouves[ $essai ] = true;
				}
			}
		}
		return 1 === count( $trouves ) ? (string) key( $trouves ) : null;
	}

	/**
	 * Un prénom en minuscules et sans accents : « Hélène » devient
	 * « helene », « Œdipe » « oedipe ». La table est celle du navigateur.
	 */
	public static function cle( $texte ) {
		static $table = null;
		if ( null === $table ) {
			$table = array_combine(
				preg_split( '//u', self::ACCENTS, -1, PREG_SPLIT_NO_EMPTY ),
				preg_split( '//u', self::NUES, -1, PREG_SPLIT_NO_EMPTY )
			);
			$table += array( 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss', '’' => "'" );
		}
		$texte = (string) $texte;
		return strtr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $texte, 'UTF-8' ) : strtolower( $texte ), $table );
	}

	/**
	 * Les parties d'un prénom ou d'un nom qui se jugent : « Jean-Pierre »
	 * en donne deux ; une initiale (« J. », « P ») n'en donne aucune. Null
	 * pour ce qui n'est pas fait que de lettres, ou qui joint deux personnes :
	 * « Jean, Marie », « Jean et Marie » se signalent déjà comme telles.
	 */
	private static function parties( $texte ) {
		$texte = trim( (string) $texte );
		if ( '' === $texte || ! preg_match( '/(*UCP)^[\p{L}\s\'’.-]+$/u', $texte )
			|| preg_match( '/(*UCP)\s(?:et|and)\s/u', $texte ) ) {
			return null;
		}
		$parties = array();
		foreach ( preg_split( '/(*UCP)[\s-]+/u', $texte, -1, PREG_SPLIT_NO_EMPTY ) as $partie ) {
			if ( '.' !== substr( $partie, -1 ) && mb_strlen( $partie, 'UTF-8' ) > 1 ) {
				$parties[] = $partie;
			}
		}
		return $parties;
	}

	/** Une lettre trois fois de suite, quelle que soit sa casse ; les deux premières telles que tapées. */
	const TRIPLE = '/(*UCP)((\p{L})\2)\2+/iu';

	/**
	 * L'avis sur le prénom d'une personne, ou rien.
	 *
	 * @param string $prenom Le prénom tel que tapé.
	 * @param string $nom    Le nom, pour voir s'il n'est pas le prénom.
	 * @param string $qui    « du responsable », « du co-auteur »…
	 * @return string[]
	 */
	public static function avis( $prenom, $nom, $qui ) {
		$prenom  = trim( (string) $prenom );
		$parties = self::parties( $prenom );
		// Sans mbstring, la casse d'une lettre accentuée ne se lit pas : pas
		// d'avis plutôt qu'un avis faux.
		if ( empty( $parties ) || ! function_exists( 'mb_strtolower' ) ) {
			return array();
		}
		$l        = self::listes();
		$inconnue = false;
		$cles     = array();
		foreach ( $parties as $partie ) {
			$cles[] = self::cle( $partie );
			if ( ! isset( $l['connus'][ end( $cles ) ] ) ) {
				$inconnue = true;
			}
		}
		// Un nom de famille fréquent tapé comme prénom, un prénom comme nom :
		// les champs échangés. Un prénom inconnu, lui, est rare ou étranger,
		// et l'on se tait.
		if ( isset( $l['noms'][ implode( '_', $cles ) ] ) ) {
			$nom   = trim( (string) $nom );
			$siens = self::parties( $nom );
			$tous  = ! empty( $siens );
			foreach ( (array) $siens as $partie ) {
				$c = self::cle( $partie );
				if ( ! isset( $l['connus'][ $c ] ) || isset( $l['ecartes'][ $c ] ) ) {
					$tous = false;
				}
			}
			if ( $tous ) {
				return array( sprintf( 'Le nom %1$s (« %2$s ») est d’ordinaire un prénom : merci de vérifier que le prénom et le nom ne sont pas inversés.', $qui, $nom ) );
			}
		}
		// Une lettre trois fois de suite : la forme connue qu'elle cache, partie
		// par partie, la lettre doublée d'abord (« Jeannne », « Jeanne »),
		// puis simple. Une autre partie inconnue ne fait pas taire l'avis :
		// la faute, elle, est sûre.
		$base = $prenom;
		if ( preg_match( self::TRIPLE, $prenom ) ) {
			$morceaux = preg_split( '/(*UCP)([\s-]+)/u', $prenom, -1, PREG_SPLIT_DELIM_CAPTURE );
			foreach ( $morceaux as $i => $partie ) {
				if ( ! preg_match( self::TRIPLE, $partie ) ) {
					continue;
				}
				$trouve = null;
				foreach ( array( '$1', '$2' ) as $par ) {
					$essai = preg_replace( self::TRIPLE, $par, $partie );
					if ( is_string( $essai ) && isset( $l['connus'][ self::cle( $essai ) ] ) ) {
						$trouve = $essai;
						break;
					}
				}
				if ( null === $trouve ) {
					return array( sprintf( 'Le prénom %1$s (« %2$s ») porte trois fois de suite la même lettre : merci de vérifier.', $qui, $prenom ) );
				}
				$morceaux[ $i ] = $trouve;
			}
			$base = implode( '', $morceaux );
		} elseif ( $inconnue ) {
			// Chaque partie inconnue doit être une faute de frappe sûre d'un
			// prénom connu ; une seule qui ne l'est pas, rare ou étrangère, et
			// l'on se tait.
			$corriges = array();
			foreach ( $parties as $partie ) {
				$c = self::cle( $partie );
				if ( ! isset( $l['connus'][ $c ] ) ) {
					$k = self::voisin( $c, strlen( self::cle( mb_substr( $partie, 0, 1, 'UTF-8' ) ) ) );
					if ( null === $k ) {
						return array();
					}
					$corriges[ $c ] = $k;
				}
			}
			$morceaux = preg_split( '/(*UCP)([\s-]+)/u', $prenom, -1, PREG_SPLIT_DELIM_CAPTURE );
			foreach ( $morceaux as $i => $partie ) {
				$c = self::cle( $partie );
				if ( isset( $corriges[ $c ] ) ) {
					$k              = $corriges[ $c ];
					$forme          = isset( $l['accentues'][ $k ] ) ? $l['accentues'][ $k ] : $k;
					$tete           = mb_substr( $partie, 0, 1, 'UTF-8' );
					$morceaux[ $i ] = $tete . mb_substr( $forme, strlen( self::cle( $tete ) ), null, 'UTF-8' );
				}
			}
			$base = implode( '', $morceaux );
		}
		// Partie par partie, les séparateurs gardés : « Jean-Francois »
		// devient « Jean-François », la capitale tapée reste la sienne.
		$morceaux = preg_split( '/(*UCP)([\s-]+)/u', $base, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $morceaux as $i => $partie ) {
			$c = self::cle( $partie );
			if ( isset( $l['accentues'][ $c ] ) && $c === mb_strtolower( $partie, 'UTF-8' ) ) {
				$morceaux[ $i ] = mb_substr( $partie, 0, 1, 'UTF-8' ) . mb_substr( $l['accentues'][ $c ], 1, null, 'UTF-8' );
			}
		}
		$propose = implode( '', $morceaux );
		if ( $propose !== $prenom ) {
			return array( sprintf( 'La graphie du prénom %1$s (« %2$s ») ne semble pas habituelle : s’écrit-il « %3$s » ? Merci de vérifier.', $qui, $prenom, $propose ) );
		}
		return array();
	}

	/** Les mêmes listes, pour le formulaire. */
	public static function pour_le_navigateur() {
		return array(
			'accents'   => self::ACCENTS,
			'nues'      => self::NUES,
			'connus'    => preg_replace( '/\s+/', ' ', trim( self::CONNUS ) ),
			'ecartes'   => preg_replace( '/\s+/', ' ', trim( self::ECARTES ) ),
			'noms'      => preg_replace( '/\s+/', ' ', trim( self::NOMS ) ),
			'rares'     => preg_replace( '/\s+/', ' ', trim( self::RARES ) ),
			'clavier'   => self::CLAVIER,
			'court'     => self::COURT,
			'accentues' => preg_replace( '/\s+/', ' ', trim( self::ACCENTUES ) ),
		);
	}
}
