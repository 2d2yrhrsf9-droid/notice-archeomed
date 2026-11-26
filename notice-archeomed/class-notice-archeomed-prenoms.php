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
 *  - deux lettres échangées (« Jaenne »), une touche voisine sur un
 *    clavier français (« Jeanbe ») ou une lettre oubliée (« Ccile ») : si un
 *    seul prénom connu est à cette distance, on le propose. La première
 *    lettre n'est jamais en cause, ni un prénom de moins de quatre lettres.
 *    Une lettre de trop ne se relève pas, hors la lettre triplée : elle fait
 *    trop souvent un autre prénom, rare et juste (Jehanne, Roser, Taras).
 *    Un prénom rare mais donné en France, à une faute d'un prénom connu
 *    (Margaud, Jeane), est tenu pour juste : la liste RARES les réunit,
 *    tirée du fichier des prénoms de l'Insee (2025).
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
	 * du fichier de l'Insee qui sont à une lettre échangée, à une touche
	 * voisine ou à une lettre oubliée d'un prénom connu, et quelques prénoms
	 * étrangers dans le même cas (Petr, Pere, Mercè, Judit, Arnau).
	 */
	const RARES = <<<'LISTE'
aalia aaliah aaliya aallyah aalya aalyah aalyiah aanya aara aaya aban abane abas abass abdalah
abdalla abde abdelah abdelakim abdelazis abdelhalim abdella abderahmane abderrahman abderramane
abderrhamane abdillah abdo abdoulay abdu abdul abdulah abdulla abes abigaele abla aboubaka
aboubakr aboulaye abril abubacar abubakar acha achile achley acil acya adaam adah adams adane
adao adda adeel adei adelia adelin adelle ademe adhan adhane adia adian adiana adiel adim adis
adiza adjan adlene adline adna adnae adolph adon adonia adonie adra adria adriaan adriani
adriene adrina adya adyan aelia aelie aelina aeline aelle aely aelya aelye aelyna aeon aesa
aewen aeya afan afia afnan afnane afraham agan agana agate agath agnel agnela agustin ahad ahana
ahed ahlan ahmar ahmes ahna ahya aian aiana aiba aidah aifa aila ailan ailee ailie ailsa aily
aima aimed aimi ainoa ainoha aira airelle aisata aisatou aislinn aissia aita aiya aiza aizen
ajad ajan ajana ajla ajlin ajna akan akana akcel akcil akia akil akima akina akio aksha aksim
alae alai alaia alane alani alara alassan alaya alaz albans albertin albi albie alce alda alden
aldin alea aleandra aleandro aleane alecia alehandro aleia aleina aleix alem alena ales
alesandro alesia alesio alessa alessi alette alev alexan alexandar alexandr alexandrie alexe
alexei alexian alexien alexin alexsandro aleya alfa alhan alhem aliaa aliah alian alias alic
alica alicja alie alifa aliha alija alijah alim alin alinor alis alise alisea alisee alisia
alisja alisya alita alixia aliyan aliz aliza alizon alkan alkis alla allma allya allyah almee
alna aloha aloma alon alona alone alonso aloy alric altin aluna alva alvanie alvi alvine alwen
alyan alyha alyia alyiah alyna alys alysa alyse alysea alysia alyss alyxia amable amad amada
amado amadu amae amale amali amaly amalys aman amana amance amande amandin amane amans amanza
amao amary ambrose ambrosine amed amela amele ameli amelien amelin amelis amelja amell amen ameo
amer amicia amid amida amie aminat aminta amisha amissa amit amiya amla amma amna amne amni amon
amor amora amra amre amri amrine amsa amycia amyssia anaa anabel anabele anaele anaelie anaell
anai anaia anaid anaiz anane anar anase anastacia anastase anastazia anatol anay anays andde
ander andra andras andres andrra anea anel anela aneline anelise anelle anette anfel anfele
angeli angelia angelic angelie angelien angelin angelise angell angels anges angi angy anha anic
anice anie anifa anik anine anisa anise aniya anja anka annabele annael annaele annela annelie
annelies anneline annelle anni annia annis anoa anoar anonciade anoucha anour anoushka ansa
ansel anselm ansi anta ante antea anthon anthoni anthonn antime anto antonela antonie antonn
antonu anuar anyelo anys aona apdil apoline apollin aqsa aram arame aran arcene archi ardian
ardiana arel aren arfa arian ariano arie ariele ariette arille arin arina arine ariz arkam
arkane arkene arla arlen arlete arman armanc armane armele arnau arnaut arold arsa arsen arta
arthis artus arys asad asan asel aseline asen asha ashly ashraf asia asil asiya asja asra assata
assatou assim assita asta aswa atena atenais athanas athea athen atlan atman atmane atmen aton
aubery aubry auda audelia audelie auden audry august aurane aure aurela aureline aurella aurelle
aurian auric aurine aurlane avan avel avelina aveline aven avigael avigaelle avigail avin
avraham aweb awel awena axele axeline axell axil ayaa ayaan ayad ayae ayane ayas ayat aycan ayce
ayda ayen ayha ayia aykan aylah aylane aylem aylen ayli aylie aylon aylone ayma ayme aymeri
aymerik aymeris aymric aymrick aynaa aynes ayra ayran ayron aysen ayssa ayton ayub ayvan ayven
ayza ayzan ayzen azae azam azan azar azedine azel azele azelie azelina azeline azem azia azid
azilis azis azriel azza azzdine badi badia badie baha bakar baltazar balthasar baptist baris
barthelem bary bashir basil batist batistin baudoin bauduin bayan bazile beatris beila bela
belal belina belma benedict benedite bengy beniamin benie benjy beny benyamin bera beranger
berard berfin berin bernadete bernadine bernardin bernie berrin berta berte bertie bertile
bertina berton bertran bertrane berty bethy betina betti bety beya beyda bice biel bilell bili
billi bily bitou blaize blanca blandin blen bleuen bluenn boby bochra bonie bonne bouchta
boushra bradi brady braim brajan bran brando brayn brenan briag briec brieu brieux brigite
brigithe britany brithany brook bryce buchra buna cadia cael caela caelia cagla caila caine
calen calev calin caliste calixta camela cami camie camile camill camron candide candie canelle
capucin caren carin carlane carle carlene carles carlie carlina carline carly carlyn carlyne
carma carme carmel carmo carolan carolin casandra casey cassi cassiope cassis catalea catalia
catalin catalya caterine catherin catia catie catleen catleya cattia catty caty cedrik cedrine
cedrix celien celiha celin cellia cely cerine cesarie ceslaw cezar cezarine chada chadi chadli
chafia chahd chahin chahines chahir chahnez chaib chaid chaida chaila chaim chaine chainez
chaira chama chane charki charle charlee charleen charlen charlet charlin charlise charliz
charlye charlyn charmette charmine chaya chayam chazia check chedi chedine cheik chelcy chely
chemsy cherin cherly chery cheyene chikh chima chira chjara chleo chrif chrifa christ christain
christan christell christia christiaen christien christin christinne chrystell ciarah ciline
cintia cira cirine cladie clady clair clare clarene clarie claudi claudien claudiu cleia clem
clemen clementin clette cleya clhoe cliford cody cole colene colins colomb colyn constan corali
corie corin corry cozette cris crista cristel cristele cristian cristiane cristie cristine
cristophe cristopher crys crysta crystel crystele crystelle cyndi cyntia cyprine cyriele
cyrienne cyrile cyrill czelaw czeslas daan daba dabia daby dael dafina dagan daha dahia dahila
daia daian daila daina dainna dais dajana dala dalhia dali dalis dalla dallia daman dame damia
damjan dane dang danh dani danie danil danila danilla danis danka dann danna danye dara dareen
darel darell daren darene dari darie daril daris darko darly darry dary daryo daud davi davia
davin davis davud daya dayaan dayah daylia dayna dayne deana deborha dehra delal delan delphie
deni denia denice deniss denize deny denya denyce denyss derick desir devi devon devora deya
dhalia diaa diaba diabe diae diamila dian dianga diara diba dibril didie diena dieneba dila
diman dinaa dine dinna dion divin djael djalal djalel djalila djamil djana djanna djeneva djmel
djmila dmitri doga doina dolan domenic dominico dominik domitile dona donan doran dorene doriand
dorien dorin dorina dorka dorlan dorlane dorone dorotea dorothe dorya drin dris dryan duena dune
dunia dwayn dwyane dyan dyana dyane eather eban eben edan edann eddi edeline edelyne edie edin
edine edite editte edma edme edmon edmone edon eduard edvard edwan edwar edwig edwine efna egan
ehan ehden ehtan eila eilan eilee eily eina eissa eiya eiza ekin elaa elae elain elaina elais
elaka elalie elan elana elane elanie elanor elanore elay elayna elda eldana eldin eldina eleen
eleha eleis elem elen elenor elenore eleonor elfi elfride elfried elga elha eliab eliad eliah
eliann eliaz elida elide elidie elien eliesa eliez eliha elija elijha elim elin elinah elior
elioth elira elis elisabet elisca elisea eliss elisse eliyah eliz elizabet elize elizee elke
ellan ellana elle elli ellio ellioth ellis elma elmie elna eloa eloah eloana eloann eloanne eloe
eloen eloha elohan elohann elois eloize elon elone elorie elory eloua elouna eloy else elsee
elsi elsia elson elssa eluan eluna elvia elvie elvin elvir elyad elyan elyasa elyaz elye elyesa
elyha elyn elys elysea elyza elyze eman emanuella emanuelle emee emelle emelyn emerik emeris
emia emila emili emiliene emiline emille emin emina emline emlyne emmanuela emmanuele emme
emmelie emmi emra emric emrick emry emya enae enao enda enee enel eneo enez enguerand enguerran
enia enid enoa enoah enoal enoh enor enric enrick enso enzi eona epona eran erel erhan erico
erij erind erio erique eris erisa erla ernes eron ersa ersan ersin erys erza esaie esen esme
esna estela estele ester estevan etann etel ethem etienette etna ettan eugen eugena euphrase
evah evahn evana evane evanh evelie evelin evely evina evline ewann ewin exan eyan eyana eyas
eyla eylen eyna eyven ezechel fabiene fadia fadil fadima fadl fahil fahima fahmi faid faida
faila faim faima faisa faiz fama fana fanie fanni fantin fanya faousi faozi fara faran farda
fardi fared farez farha faria fariha farila farima faris fariza farna farok faruk fata fatah
fath fatha fathia fati fatia fatiah fatila fatim fatimat fatin fatina fatine fatiya fatmata
fatoma fatoumat fatuma fatumata fauzi favian favienne favio fayal fayim fayna faysal fayz fazia
fazila feda fedy felice felixia ferda ferdy ferie fida filbert filip fina fiora fiore fiorine
fiza flavine floe flona flor floraine floran florane floranne florene flori floria florien
florimon florin florina florinne florus foad fohad fousia fouzi fozia fran franca francelin
francesa francie francisa francise francka francy frane frania franki franky frans fransisco
freddi frederi frederico fredie fredj fredrik frey fuad gabi gabriell gabril gaele gais gaja
gala gali gani gaspare gavin gavriel gaye gaylor geffrey genael genna geofrey geofroy georgi
georgie geral german germanie germine gert gertrud ghilain ghilaine ghislane ghisline ghizlaine
ghylaine ghyslain gian giano gida giles gilia gilian gille ginete gioanni giorgi giovan giovana
giovann giovano giovany gisel gishlaine gislain gislhain gismaine gizele gizelle glady gleb
glodia glwadys godfroy goeffrey golbert golda golven gorge gradie grasiella graziela gregor
gregorie grey guenaele guevan guilain guilane guilaume guilem guilia guilian guiliana guiliane
guiliano guilio guislain gulia gulian guliana gustav guylain guyliane guyslaine gwenaele gwenal
gwenda gwenel gwenhaelle gwennaele gwenoline gwenval gyslaine habi habid habida hacen hada hadel
hadia hadj hadry hadya hael hafca hafif hafiz hafiza hafs haida haile haily hailye haim haina
haja hajra hakila halan haled haley hali halia halid halil halley halma halmi hama hamda hamdi
hame hami hamim hamir hamis hamsa hanad hanah hani hanis hannae hannan hany hanz hardy harun
hary hasen hassna havid havida haya haydn hayem hazem hebert hedwig heena helea heli helois
hemen hendy heni henia heny heran heria herine herline hermande hermin hery hevi hichma hifa
hila hilarie hildegard hipolyte hisham hishem hiva hlima hnia hoda hodaya holy honor honorin
horia hortence houd houra housine huda hugette hughes hughette hugue hulda husein huseyn
hyacinte hyden iana iane iann iara ibragim ibraim ibtisam ibtisem idaline idan idem idene idia
idiss ihlan ihlem ikam ikra ilaan ilahn ilaina ilam ilane ilaya ilayna ilem ilen ilene iles
ilhana ilhane ilia ilie ilin ilina iliona ilisa iljana ilkan illa illana illina illoa ilman ilna
iloa iloan iloha ilya ilyaas ilyad ilyah ilyam ilyase ilyaz ilyese ilyna ilyne ilys imae imael
imana imann imna imra imrah inaa inais inan inata inayat inayha inda indi inea ineia inesa inga
inha insa insia insya inya ioan ioana ioane ioann ioen iona iqra iram irane iren iria irie iriss
irman irmane irna irsa isaam isabela isabele isac isael isaelle isaia isak isalie isam isaq isea
isha ishai ishal ishan isia isiah isidor isie isla issac issao ivaan ivah iyan iyana iyane iyas
iyass iyes iyess izaac izabel izabella izabelle izai izaiah izaline izhak izhaq izis izra jaad
jabier jace jacen jacque jacquelin jacquemine jadd jadon jael jaelle jaha jahden jahe jahelle
jahina jahis jahna jail jaina jais jaki jakie jaky jalal jalel jali jalila jame jami jamil jamin
jams janah janele janelie janell janete jania janic janik jann jannat janne jannette jannic
jannie jannik jannine jannot janny janos jaoad jaoua jaqueline jaques jaquy jaren jasim jasin
jaslyn jaume jaxon jayde jaydon jayen jayzon jaze jazmine jazon jeane jeanick jeanie jeaninne
jeannic jeannik jeannne jeanot jeef jeferson jeffry jehane jehann jehna jena jenah jenan jenane
jenie jenine jennat jennine jeny jenyfer jery jesica jess jessi jessia jeyan jhana jhoan jhon
jhonathan jhonny jhony jian jiana jianny jihan jihanne jilan jilian jiliane jilyan jimmi jimy
jinane joab joah joahn joam joane joanes joany joas jocelin jocleyn joddy joee joeffrey joelie
joeline joelyne joffre joffret jofrey joha johanan johane johanes johna johnatan johnattan johnn
joleen jona jonan jonatan jonaz jonhy jonn jonnathan jonny jony joran jorane jord jordans jorg
jori joric jorry jory josane josanne joscelin josceline joscelyne joseba joselin josep josephin
joset josete josia josiah josie josine josline joslyne josquin joss josse josselyn josua josuan
josuha josy josyan josyne jouan jouanna joye joze jozef jozefa jozette joziane judd judit juge
jule julen julette juli julina julinne julitte julo juluan julya julyna juna jury jyan jylian
kabil kada kade kadel kadi kadia kadiatu kadidja kadim kadja kadjatou kadri kael kaela kaelia
kaelle kaeron kafia kahim kahin kahlan kahlil kaia kail kaila kaim kaima kain kaira kala kalan
kaled kalel kalia kaliah kalid kalil kalila kalis kalya kalyan kama kamela kami kamilla kamla
kamron kana kanya kaona kaotar karele karell karem karia karil karma kasandra kasey kasija kasim
kassi katalea katalya katel katelle katerine kathlen kathlene kathrine kati katina katja katleen
katleya katlheen katrin kattia katty kavin kaya kayal kayan kaydon kayen kayil kayila kayina
kayl kaylah kayli kaylian kaylis kaylla kayllah kayn kayra kaysen kayton kayven kayz kayzen kean
keann keden kehan kehlia keila keilan keily keilya keira keiran keis keity keiyan kela kelan
kelhan keli keliah kelie kelina kelis keliya kella kellan kellys keloan kely kelyah kelyna kelys
kena kenai kenay kenda kendi kendy kengy keni kenn kenna kenni kensi kensy kent kenz keran kery
kesia kesiah kesy kethy keti ketia kety keya keyan keylah keyli keylis keylla keymia keyn keysia
keza kezia kezian khadia khadidia khadiya khail khaina khais khald khali khalia khalie khalif
khamis khera kheria khiera khira kiana kiera kilan kilia kilina killan killya kilya kimberle
kina kinsley kira kiran kirsty kiyan klelya kleya kolyan krim krisha kristian kristophe krysta
kyana kyden kyla kylan kylann kylia kyllan kyllia kymie kyra kyron laana ladislaw laena lahra
lahsen laian laiba laifa laika laim laima laira lais laissa lala lale lali lalia lalla lalya
lama lamin lamis lamys landy lanha lania lanna laona lareen laren larine larisa lars lary lasana
latif latufa lauane laude laudine laugane laula launa lauraine laurann laurea laureine laurelie
laurens laurentie laurentin laurenz lauri laurian laurien laurinne lauris laurna laurye layaa
layaan layah layal layba layha layia layka layl layn layne laynna layra lazar lazard leahna
leahne lean leander leann leaticia leatitia lebna leelo leely leha lehny leian leika leil leine
leis leith lejla leli lelie lella lelya lemia lenae lenais lenha lenhy lenie leno lenoe lenore
lens lenya lenz leobard leocade leoh leoni leonice leonid leonine leonis leonise leonn leontin
leopol leopoline leora leovan lery leslye letisia letitia levan lexi leyal leyan leyia leyl leyn
leyns leys lhana lhena lhiam lhyam liah liahm lian liandro liane liann lianne lias liba licia
liciana liciano licio lida lidiane lidio lidivine liee lien liena lies liette ligia liha lihan
lihana liia lijana lika lilae lilan lilana lilane lile liliah lilja lilla lilli lillia lillo
lilo liloa liloe liloo lilu lilwen lima linaa lindsy linea link linsay linsey lion liona liou
lira liria lisane lisea lisete lisia lisian lisio lisna lita litana liva livai livi liwen liwenn
lixia liyam liyan liyna lizae lizandro lize lizette lizi lizia liziane lizie lizon llana lliana
llona lluis lluna llyah llyana loah loam loar loay lobna locadie loda lodia loen loena loene
loenie loenzo loeva loey logann loham lohana lohane lohanne lohis loica loice loid loide loik
loika loina loine loisa loiss loiz loiza loize loli lolia lolie lolly loly lolya loma lona lonan
long lonie lonis lonni loraine loran lorane lore lorea loreen lorelie lorenda lorens lorenso
lorenz loreta loria lorian loric lorien lorin lorrain lorriane lorrine lors lorya loryc lorye
losa loua louange louba loue louei louhna loui louisane louiss louize louizon loula lounea
lounia lounna louva louy lovan lovia lowe lowel loya loyan loyana loyann loyna loys lozea luam
luane luann luanna luanne luara luay luba lubna lucaz luci lucian lucil lucina lucine lucinne
lucja lucka ludie ludovine ludvic ludwin ludwine ludy luixa luiz luiza lukaz luli lulia lulian
luliana luma lunaa lune lunis lurel lusia luvia luvio luyana lyaa lyaan lyahm lyahna lyan
lyandre lyane lyanha lyann lyas lyat lycia lyciana lyciano lycio lyda lydiane lydivine lyed lyel
lyena lyette lyha lyia lyiah lyiana lylan lyli lyliah lylla lylo lyloo lyma lynea lynh lynna
lyria lysia lysian lysie lyvai lyvie lyza lyze lyzia lyziane lyzie mabelle macine macy mada
madalena maddi madelie madeo madhi madi madian madiana madiane madiba madie madiha madilyn
madilyne madin madine madiya madji madlie madline madly madlyne madona madyline maedy maeh maele
maeley maeli maelic maelien maelise maeliz maell maella maelly maellya maellye maelon maelone
maelye maelyn maelyse maen maesha maevin maewa maewan magdalen magdeline magdi magdy magy maha
mahar mahau mahdy mahed mahee mahel mahelle mahi mahid mahlon mahlone mahmadou mahmud mahon
mahrane mahreen maian maiana maiane maianna maid maida maide maie maiha maija maik maika maiky
maila mailou mailya mailye mailyn mailyne maimuna maira mairam mairame maire mairon maisa
maisane maissae maissan maiya maja majd majdi maje majorie maka mala malal malan malau malco
male malea malene maleo maley maleya malha mali malic malica malicka malie malina malio malka
malkia malle mally maloa maloh malohe malon malori malte malu malvin malwen malwenn maly malys
mamadu mame mamia mamou mamoud mana manali manao manda mandi mane manea manek manele manell
manella maneo mano manoha manol manoli mansur manuele many maoh maona maorie maory maral marame
maran marat marcela marcele marcellie marcellien marck marcu mareen marga margau margaud marge
mari mariah marianick mariannik marica mariel mariele marig mariia marija marik marila marily
marinna marinne marino marita marize marja marjan marjana marjane marjanna marjoline marko
markus marleen marlen marlette marlie marline marlise marlo marly marlyn marlyne marlys marni
marnia marnie maro maroa maroi maroia maroin marouan marry mars marsel marso marte marti martins
marton martta marvel marvy marwah marx maryan maryanick marye marylee marylen marylie marylis
maryn maryo maryon marys maryvone maryze masha massa massim mata matar mate mateho matei matel
matew mathei mathia mathide mathie mathiss mathy mathya mati matia matida matieu matin matina
matine matio matiss matiya mats mattei mattew matthia matti mattias mattieu mattin matty mattys
maty matya maur maura maure maurene maurin maurise mauve mavel maveline mavy mawa mawan mawen
mawenn maxen maxene maxense maxi maxie maximilie maxy mayan mayann mayda maydon maye mayel mayer
mayla maylah mayle maylene mayli maylia maylin maylise maylo maylou mayon mayram mayreen mayrine
mays maysa maysaa maysane mayson mayssae mayssan mayton mayz mazna meana meane medi mediba
mediha medin medni medric medy meen meggan meghna meia meila meina meivyn meiya mejdi melan
melane melani melchor meldin melha meli melian meliane melica melida melie meliha melin
melisandre meliss melissande meliza melk melle mellia melodi melona melos melsa melua melvy mely
melyan melyha melyn melys melyss memet mena mency mendi merce meria merima merin merina merine
merjem meruem mery merya meva mevan meve mevin mevyn mewan meya meyan meydi meyla meyna meyram
meyrem meyssa mhamed mhedi mhedy mialy mian miana miassa micael micaela michal michee michela
michelin micke mickel micky migel miguette miha mihaela mihan miia mija mika mikal mikel milaa
milaan milah milane mile milea milha mili milia milka milly mily milyan mimouna miora mirab
mirah miraj mirela mirelle miria mirielle mirna missa mitsy moad moan mogan moha mohad mohamad
mohamadou mohan mohmed mohrad moina molene molla moly mondy moni monir moran morane morann
morvan mose mostafa mostapha moua mouad mouhamad mouni mounis mouraz mousa moya muad muguel
muhamad muhamed mulan muna munir murad muriele mussa myassa myia mylah mylee mylen myli mylie
myline mylis mylon mylone myna myran myria myriel myrielle myrtile naaim nabel nabi nabia nabyl
nacia nacio nader nadera nadi nadie nadim nadja naele naelie naell naem naer nafila nagia nagla
nahe nahei nahila nahim nahis nahtan nahym naib naid naida naik naika naimi nair naja najah naji
najia najih najla najm najma nakib nala nalha nali nalia nalla nalle nalya nama nami namia namie
namy nana nandy nane nany naofel naoh naoial naola naolie naoly naoma naome naos naouel nara
naria naser nasera nasia nasim nasima nasir nasira nassef nassia nassila nassir nassita nassma
nassra nastasja nastazia nata natael natala natale natali natanael natanaelle nataniel natasa
nate nathae nathali nathanaele nathanel natia natnael nattan natteo nava nawa nawele nawell
nayal nayan naye nayem nayha nayhan nayil nayka nayl nayma nays naza nazia nazir nazmi neal neel
neels neema negla nehla neia neika neils neily neim neima neiya nejla nela nelcon nele neli
nelie neline nelle nelli nelma nelo nels nelso nely nema neola neoline neomie nerine nesim
nesrin ness neya neyl neyma nhael nhoa nholan niah niama niar nibel nica nico nicol nicolae nida
niel niele niha niham nihan niki nikol nila nilan nilann nile nill nima nimah ninel nini ninno
ninog nira nirma nisar nisrin noahm noahn noami noann noar nobert noee noeli noelien noelli
noely noem noema noemia nohla nohra noia noira nolah nolahn nolanh nolen nolha nolia noline
nolla nolyan noman nomi nona nono nooa noran norcine nordin noreddine norha nori norine noris
norlan norsine nouar noufel nourcine nourddine nourdin noureddin nouredine nouri nourine
noursine nowen noya noyan nuna nura nuria nyah nyla nyno obeline ocean oceann odete odil odine
ohan ohian oiana oifa okhan olaf olicia olie oliva olliver olta olya oman omayma ombline omra
oran orana oranne oria orian oris orlan orlana orland orlanne ornela ornelia osmane ossama otman
otmane ottman ottmane otton ouaid ouali ouma oumama oumaya oumra ourda ousama ousman oussam owan
pacom padrig palma palmyr paol pascalie patric patrik patrisia paulien pavlo pelage pere perine
perl perrette perrick petar petr petro pharel philbert philipe philipine pier pierette pieric
pierick pierine pierrik pola polo precilia precilla precillia prescila presilia presillia
pressilia pricilia pricilla pricille pricillia priscila priscile prisillia proper qasim quiterie
raba rabab rabat rabha rabi rabya rachda rachele rachi rada radja radouan rafah rafal rafel rafi
rafia rahan rahel rahia rahil rahyan raia raid raida raim raina raisa raiss rajan rali raly rama
ramadan ramina ramiz ramna ramond ramsi ramzan rana randa rani ranim rany rashel rashid rathana
raya rayah rayana rayban rayd rayhaan rayim raym rayma raymon raymone rayn rayna raynal raynale
rayne rayyan rayyana razan razi razia read rean reane rebeca rebeka redouan regan regia reginal
rehan rehane reina reinald reis rejan remo renad renal reva reyane reynal reza rhama rhianna
rhim rhoda rian rianna riaz ricard rich richad richelle rico rihad rihan rihana rihann rija
rijad riman rimane rinda riva riya riyanna riyas riza roaa roald roan roane roann roanne roby
rock rocky roda roderic rodine rodny rodolph rodrick rody roge rogee rojan rojda rolan rollan
roma romains romanie rome romel romi romin romuld rona ronin ronn rosale rosali rosaline rosane
rosanne roselie roselin rosemay rosemond rosenn roseta rosi rosia rosin ross rouan roxan roxann
roxy royan roza rozalia rozalie roze rozen rozie rozy ruan ryane ryaz ryda ryhan ryhana ryiad
ryna saana saba sabaa saban sabha sabi sabia sabien sabin sabina sabir sabr sabra sabria sabrie
sabrin sacia sada sade sadi sadik sadim sadiq sadiya sadja sadjo sadra sady safah safi safiha
safine safwa safya safyia saga sagia sagra sahar sahid sahina sahna sahyna saif saifa saim saima
saina saira sait sajaa sajda saji sajia sala salam salha salhia sali salia salih salime salina
salira saliya salla salle salmah salmata salmi saloa saloia salomo salou saloy salvator saly
salya sama samah saman samanta samid samie samiel samiha samil sammi samna samra sanad sanaf
sanah sanai sancie sanda sandi sandor sandri sandu sane sani sanja sanka sann sanna sansa sante
santiano sany sanya saran saray sarha sari saria sarina sarine sariya sarta sasa sata satia
satie sava savanah savanna savia savine savio sawan saya sayma sayna sayra scarlet scolastique
scoty seba seban sedan sedine seena sefkan sega segolen seila seli selia selio selly selya selyn
selyna sema semi semin senna seraphie seren serfe sergi sergo serigne serin serrine servan
servine seryne sety sevag sevane seven sevrine seyan seylan sfia shaan shadi shae shafi shai
shaia shaila shaim shain sham shama shan shania shanie shanise shanley shann shanonn shanya
shaona shara shauna shaya shayan shayla shaym shayn sheily sheima sheley sheli shella shellsy
shely shem sherazad sherin sherly sherone sheryn shima shina shirly shun shurley shyana shyma
shyna siam siame sian siane siar sibyle sidar sidgey sidjey sido sidoine sidy siegfrid siegried
siem siena siera sifa sifra siga sigfried siha sihan sihane sika silan silene silia siline silly
silva silvan silver silvi silvin sima simane simeo simine simo sina sinaa sinam sinem sinna
sioban sion sirin sirra sita siyam siyem slah slim sliman slone soad soah soahn soaig soana
soanh soban sobhan sobia soenn sofi sofie sofine sogan sogane soha sohad soham sohayl soifa
soina soisic sokaina sola solam solane solanne solayman sole soleen soleyman soleymane soleymen
solie solin solune somaya someya somia sona sonay sonda soni sonna sopie sora soria sorian
soriane sorine sorya soua souan souane soufian souhyl soukina soulayma souleman soulemane
soulyman soulymane soumya soupaya soya soyan soyane soyann staci staecy stana stane stanilas
stanislaw stanly steban stedy steev stefane stefani stefen stefy stela stelia stelly stepan
stepha steren stesy stevan stevano stevee steveen stevene steves stevy suad suan suelly suhayl
sukaina sulayman suleman suleymane suleymen sulivan sullian suly sulyan sulyman sumaya sumeya
sundy sura susane susy suzan suzane suze suzele suzete suzi syan syane syanna sybile sydne syla
syldia syldie syline sylio sylla sylva sylvan sylvane sylve sylver sylveste sylvester sylvian
sylvin sylvins syna syrin taar tabita tadek tahia tahiana tahis tahlia tahlya taho tahra tahya
taia taian taiana taiba taiga taila taim tala talha tali talis talla taly talys tamar tamia
tamime tamra tamya tana tani tanis tanja tany tanys taos tark tata tatjana taya tayla taylo
tayna tedd tedi tedy teha teho teilo teina telma telo teodor teodora teodore teophane teophile
teotime terance tereza tery thai thaila thaly thalys thana thayla thelo theophil theotim thereza
therry thery thibalt thibau thibaux thimo thoma tian tiano tiao tibaud tibault tibaut tibo ticia
tidian tidjane tierry tifanie tifen tiffaine tiffani tiga tiha tileo tilo timae time timm timo
timoe timotee timoty timy tiny tisha titoan tituan tobia tofik toma tomaz tomeo tommi toms tona
tong tory toya tricha trish tuana tudy tulio tuna tyan tylan tyleo tylia tylo tyloan tylor tymo
tymoe tyna ulric ulyss umit urban vahina vaina valeri valeria vanesa vania vanna veda veena vega
veran veron vianey vianne vicenzo vick victore victorie vidian vidiane viki vikie viky vincenso
vincine violine virgina virgine vita vitor vitoria wali walis ward waren wasil wasila wasim
wassia wassima wendel wesly wilem wilfride wilheim wilhem wiliam will willa willam willams willi
williame willis willo wilow wisal wisam wisem wissel witney wolfang wyat xabier yaasir yacin
yacob yacou yaele yaelie yaell yafa yahn yahnis yaina yalina yaline yamin yamna yang yanhis
yanic yanik yanise yanni yannic yannie yannik yanns yaqin yaser yasid yasina yasine yasir
yasline yassri yazin yazmina yban ydan ydann yelen yeline yelle yhan yhann yina ylana ylang
ylena ylhan ylhann ylian ylina yllana yloa yloan yman ynais yoah yoahn yoenn yogan yohenn yohna
yolain yolana yoland yolane yoline yomna yones yonn yonnick yoona yori yosra yossef yossra youan
youba youen youga youma youn younese younn youns younse younss yousef yousouf yousr youssof
youssou youssuf ysalie ysra yuan yuba yucef yumna yunes yunis yuri yusif yusra yussef yussouf
yussra yvelin yvone yvonick yvonnic yvonnik yzaline yzee zacary zachari zahar zahi zaia zaian
zaid zaiha zain zakari zakary zakaryia zaki zalia zana zarha zaya zayah zayna zaynah zayne zayr
zayra zays zeha zeia zela zeli zemia zeneb zenep zeynel zhara zhia zhora zian ziana zida ziha
zihad zindine zine zira ziya zobida zoha zohar zoia zorha zoura zubida zyan zyneb
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
	 * échangées, à une touche voisine ou à une lettre oubliée, la première
	 * lettre gardée ; ou null.
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
		for ( $i = max( 1, $debut ); $i <= $n; $i++ ) {
			foreach ( range( 'a', 'z' ) as $d ) {
				$essai = substr( $c, 0, $i ) . $d . substr( $c, $i );
				if ( isset( $l['connus'][ $essai ] ) ) {
					$trouves[ $essai ] = true;
				}
			}
			if ( $i === $n ) {
				break;
			}
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
